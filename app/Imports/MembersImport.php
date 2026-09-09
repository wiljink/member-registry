<?php

namespace App\Imports;

use App\Models\Member;
use App\Support\MemberClassifier;
use App\Support\Registry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Imports the "Members" result set of cic_merged_registry_extract.sql
 * (identical column headers to the sample "SQL CIC output.xlsx").
 *
 * - Source columns are always (re)written.
 * - Manual columns are never touched, except the seed-if-null set which is
 *   filled once while still empty.
 */
class MembersImport implements SkipsEmptyRows, ToCollection, WithChunkReading, WithHeadingRow
{
    public int $created = 0;

    public int $updated = 0;

    public int $skipped = 0;

    /** @var array<int,string> */
    public array $errors = [];

    /** @var array<string,bool> distinct branch names encountered in the file */
    public array $branchesSeen = [];

    protected ?string $periodDate;

    protected ?string $periodEnd;

    /**
     * @param  string|null  $branchOverride  optional — forces every row to this branch;
     *                                       normally left null so each row is filed under
     *                                       its own "Branch Code" column.
     * @param  string|null  $period  the data month as "YYYY-MM"; stamped on every row.
     */
    public function __construct(protected ?string $branchOverride = null, ?string $period = null)
    {
        $date = Registry::periodToDate($period);
        $this->periodDate = $date?->toDateString();
        $this->periodEnd = $date?->endOfMonth()->toDateString();
    }

    protected function periodEndDate(): ?string
    {
        return $this->periodEnd;
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $i => $row) {
            $data = $this->normalize($row->toArray());
            $cid = $this->pick($data, ['provider subject no', 'membership number cid', 'cid']);

            if (! filled($cid)) {
                $this->skipped++;

                continue;
            }

            try {
                $this->upsert((string) $cid, $data);
            } catch (\Throwable $e) {
                $this->skipped++;
                if (count($this->errors) < 50) {
                    $this->errors[] = "CID {$cid}: {$e->getMessage()}";
                }
            }
        }
    }

    protected function upsert(string $cid, array $d): void
    {
        $member = Member::firstOrNew(['cid' => $cid]);
        $isNew = ! $member->exists;

        $branch = $this->branchOverride ?? $this->pick($d, ['branch code', 'branch']);
        if (filled($branch)) {
            $this->branchesSeen[$branch] = true;
        }

        $member->fill([
            'branch' => $branch,
            'data_period' => $this->periodDate,
            'subject_reference_date' => $this->date($this->pick($d, ['subject reference date'])),
            'title_code' => $this->pick($d, ['title']),
            'last_name' => $this->pick($d, ['last name']),
            'first_name' => $this->pick($d, ['first name']),
            'middle_name' => $this->pick($d, ['middle name']),
            'suffix' => $this->pick($d, ['suffix']),
            'gender' => $this->pick($d, ['gender']),
            'birth_date' => $this->date($this->pick($d, ['date of birth', 'birth date'])),
            'civil_status_code' => $this->pick($d, ['civil status']),
            'nid' => $this->pick($d, ['identification 1 number', 'id 1 number', 'nid', 'employment tin']),
            'mobile1' => $this->pick($d, ['contact 1 value', 'mobile1']),
            'email1' => $this->pick($d, ['contact 2 value', 'email1']),
            'spouse_first_name' => $this->pick($d, ['spouse first name']),
            'spouse_last_name' => $this->pick($d, ['spouse last name']),
            'spouse_middle_name' => $this->pick($d, ['spouse middle name']),
            'home_address' => $this->pick($d, ['address 1 fulladdress', 'address 1 full address']),
            'home_postal_code' => $this->pick($d, ['address 1 postalcode', 'address 1 postal code']),
            'business_address_src' => $this->pick($d, ['address 2 fulladdress', 'address 2 full address']),
            'business_postal_code' => $this->pick($d, ['address 2 postalcode', 'address 2 postal code']),
            'last_imported_at' => now(),
        ]);

        // Financial standing (drives the Type / Kind / MIGS / Active classifier).
        $share = $this->num($this->pick($d, ['share capital balance']));
        $savings = $this->num($this->pick($d, ['savings balance']));
        if (! is_null($share) || ! is_null($savings)) {
            $member->share_capital_balance = $share;
            $member->savings_balance = $savings;
            $member->share_capital_as_of = $this->periodEndDate();
            $member->savings_as_of = $this->periodEndDate();
        }
        $member->last_savings_txn_date = $this->date($this->pick($d, ['last savings transaction date']));
        $member->last_share_txn_date = $this->date($this->pick($d, ['last share transaction date']));
        // loan-derived inputs (delinquency, overdue_installments_12mo, loan_count) come
        // from the Loans import via Member::recomputeAggregates(), which re-runs the classifier.

        // Email in the contact block may actually sit in "Contact 1" – tidy that up.
        if (blank($member->email1) && str_contains((string) $member->mobile1, '@')) {
            $member->email1 = $member->mobile1;
            $member->mobile1 = null;
        }

        $member->save();

        // Seed manual columns once, only while still null.
        $seedUpdates = [];
        foreach (Member::seedIfNull() as $column => $resolver) {
            if (blank($member->getAttribute($column))) {
                $value = $resolver($member);
                if (filled($value)) {
                    $seedUpdates[$column] = $value;
                }
            }
        }
        if ($seedUpdates) {
            $member->fill($seedUpdates);
        }

        // Auto-derive Type / Kind / MIGS / Active from the financial standing
        // (skips any field the user has hand-overridden).
        MemberClassifier::apply($member);

        $member->recomputeCompletion();
        $member->save();

        $isNew ? $this->created++ : $this->updated++;
    }

    /* --------------------------------------------------------------- */

    /**
     * Build a lookup keyed by an alphanumeric-only, lower-cased header so we
     * are immune to whatever slug format the reader produced.
     *
     * @return array<string,mixed>
     */
    protected function normalize(array $row): array
    {
        $out = [];
        foreach ($row as $key => $value) {
            $out[preg_replace('/[^a-z0-9]/', '', strtolower((string) $key))] = $value;
        }

        return $out;
    }

    protected function pick(array $normalized, array $candidates): mixed
    {
        foreach ($candidates as $candidate) {
            $key = preg_replace('/[^a-z0-9]/', '', strtolower($candidate));
            if (array_key_exists($key, $normalized)) {
                return Registry::clean($normalized[$key]);
            }
        }

        return null;
    }

    protected function num($value): ?float
    {
        $value = Registry::clean($value);

        return is_numeric($value) ? (float) $value : null;
    }

    protected function int($value): ?int
    {
        $value = Registry::clean($value);

        return is_numeric($value) ? (int) $value : null;
    }

    protected function date($value): ?string
    {
        $value = Registry::clean($value);
        if (blank($value)) {
            return null;
        }

        try {
            if (is_numeric($value)) {
                // Excel serial or yyyymmdd
                if ((int) $value > 19000000 && (int) $value < 99999999) {
                    return CarbonImmutable::createFromFormat('Ymd', (string) (int) $value)->toDateString();
                }

                return CarbonImmutable::instance(
                    Date::excelToDateTimeObject((float) $value)
                )->toDateString();
            }

            return CarbonImmutable::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    public function chunkSize(): int
    {
        return 500;
    }
}
