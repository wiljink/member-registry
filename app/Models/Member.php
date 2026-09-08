<?php

namespace App\Models;

use App\Support\Registry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Member extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'subject_reference_date'     => 'date',
            'birth_date'                 => 'date',
            'date_accepted'              => 'date',
            'bod_res_date_confirmed'     => 'date',
            'termination_date'           => 'date',
            'share_capital_as_of'        => 'date',
            'savings_as_of'              => 'date',
            'earliest_loan_open_date'    => 'date',
            'latest_loan_maturity_date'  => 'date',
            'last_imported_at'           => 'datetime',
            'completed_at'               => 'datetime',
            'is_pwd'                     => 'boolean',
            'initial_share_amount'       => 'decimal:2',
            'initial_paid_up'            => 'decimal:2',
            'annual_income'              => 'decimal:2',
            'share_capital_balance'      => 'decimal:2',
            'savings_balance'            => 'decimal:2',
            'total_financed'             => 'decimal:2',
            'total_outstanding'          => 'decimal:2',
            'total_overdue_amount'       => 'decimal:2',
        ];
    }

    /**
     * Columns overwritten on every members-file import.
     */
    public const SOURCE_FIELDS = [
        'branch', 'subject_reference_date', 'title_code', 'last_name', 'first_name',
        'middle_name', 'suffix', 'gender', 'birth_date', 'civil_status_code', 'nid',
        'mobile1', 'email1', 'spouse_first_name', 'spouse_last_name', 'spouse_middle_name',
        'home_address', 'home_postal_code', 'business_address_src', 'business_postal_code',
    ];

    /**
     * Manual columns seeded from the source ONCE, only while still null.
     * [target manual column => callable(Member): mixed]
     */
    public static function seedIfNull(): array
    {
        return [
            'present_address'       => fn (Member $m) => $m->home_address,
            'sex_assigned_at_birth' => fn (Member $m) => Registry::genderLabel($m->gender),
            'gender_identity'       => fn (Member $m) => Registry::genderLabel($m->gender),
            'civil_status'          => fn (Member $m) => Registry::civilStatusLabel($m->civil_status_code),
            'contact_number'        => fn (Member $m) => Registry::formatMobile($m->mobile1),
            'email_address'         => fn (Member $m) => Registry::clean($m->email1),
            'date_accepted'         => fn (Member $m) => optional(Registry::parseDateAccepted($m->home_address))->toDateString(),
        ];
    }

    public function loans(): HasMany
    {
        return $this->hasMany(MemberLoan::class, 'cid', 'cid');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('cid', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('first_name', 'like', "%{$term}%")
                ->orWhere('nid', 'like', "%{$term}%");
        });
    }

    public function fullName(): string
    {
        return trim(collect([
            $this->last_name ? $this->last_name.',' : null,
            $this->first_name,
            $this->middle_name,
            $this->suffix,
        ])->filter()->implode(' '));
    }

    public function computedAge(): ?int
    {
        return $this->birth_date
            ? (int) $this->birth_date->diffInYears(Registry::asOfDate())
            : null;
    }

    /**
     * Which required-for-complete fields are still blank.
     */
    public function missingFields(): array
    {
        return collect(config('registry.required_for_complete'))
            ->reject(fn (string $field) => $this->fieldFilled($field))
            ->values()
            ->all();
    }

    protected function fieldFilled(string $field): bool
    {
        $value = $this->getAttribute($field);

        return $field === 'is_pwd' ? ! is_null($value) : filled($value);
    }

    public function recomputeCompletion(): void
    {
        $required = config('registry.required_for_complete');
        $filled = collect($required)->filter(fn ($f) => $this->fieldFilled($f))->count();

        $this->completion_status = match (true) {
            $filled === count($required) => 'complete',
            $filled === 0                => 'pending',
            default                      => 'in_progress',
        };

        $this->completed_at = $this->completion_status === 'complete'
            ? ($this->completed_at ?? now())
            : null;
    }

    /**
     * Recalculate the loan roll-up columns from member_loans.
     *
     * @param  array<int,string>|null  $cids  limit to these CIDs, or null for all
     */
    public static function recomputeAggregates(?array $cids = null): void
    {
        $agg = MemberLoan::query()
            ->when($cids, fn ($q) => $q->whereIn('cid', $cids))
            ->groupBy('cid')
            ->selectRaw('cid')
            ->selectRaw('COUNT(*) as loan_count')
            ->selectRaw('COALESCE(SUM(financed_amount),0) as total_financed')
            ->selectRaw('COALESCE(SUM(outstanding_balance),0) as total_outstanding')
            ->selectRaw('COALESCE(SUM(overdue_amount),0) as total_overdue_amount')
            ->selectRaw('MAX(overdue_days) as max_overdue_days')
            ->selectRaw('SUM(CASE WHEN COALESCE(overdue_days,0) > 0 THEN 1 ELSE 0 END) as delinquent_loan_count')
            ->selectRaw('MIN(start_date) as earliest_loan_open_date')
            ->selectRaw('MAX(planned_end_date) as latest_loan_maturity_date')
            ->get()
            ->keyBy('cid');

        // Reset the members we're recomputing, then apply the aggregates.
        Member::query()
            ->when($cids, fn ($q) => $q->whereIn('cid', $cids))
            ->update([
                'loan_count' => 0, 'total_financed' => 0, 'total_outstanding' => 0,
                'total_overdue_amount' => 0, 'max_overdue_days' => null,
                'delinquent_loan_count' => 0, 'earliest_loan_open_date' => null,
                'latest_loan_maturity_date' => null,
            ]);

        foreach ($agg as $cid => $row) {
            Member::where('cid', $cid)->update([
                'loan_count'                => $row->loan_count,
                'total_financed'            => $row->total_financed,
                'total_outstanding'         => $row->total_outstanding,
                'total_overdue_amount'      => $row->total_overdue_amount,
                'max_overdue_days'          => $row->max_overdue_days,
                'delinquent_loan_count'     => $row->delinquent_loan_count,
                'earliest_loan_open_date'   => $row->earliest_loan_open_date,
                'latest_loan_maturity_date' => $row->latest_loan_maturity_date,
            ]);
        }
    }
}
