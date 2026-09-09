<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Support\MemberClassifier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MemberController extends Controller
{
    public function index(Request $request)
    {
        $members = Member::query()
            ->search($request->query('q'))
            ->when($request->query('branch'), fn ($q, $b) => $q->where('branch', $b))
            ->when($request->query('period'), fn ($q, $p) => $q->whereYear('data_period', substr($p, 0, 4))->whereMonth('data_period', substr($p, 5, 2)))
            ->when($request->query('status'), fn ($q, $s) => $q->where('completion_status', $s))
            ->when($request->query('loans') === 'with', fn ($q) => $q->where('loan_count', '>', 0))
            ->when($request->query('loans') === 'delinquent', fn ($q) => $q->where('delinquent_loan_count', '>', 0))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(25)
            ->withQueryString();

        return view('members.index', [
            'members' => $members,
            'branches' => Member::branchNames(),
            'periods' => Member::dataPeriods(),
        ]);
    }

    public function show(Member $member)
    {
        $member->load('loans');

        return view('members.show', compact('member'));
    }

    public function edit(Member $member)
    {
        $member->load('loans');

        return view('members.edit', [
            'member' => $member,
            'options' => config('registry.options'),
            'autoClass' => MemberClassifier::compute($member),
            'lockedClass' => (array) $member->classification_locked,
        ]);
    }

    public function update(Request $request, Member $member)
    {
        $opt = fn (string $key) => Rule::in(array_keys(config("registry.options.{$key}")));

        $data = $request->validate([
            'tin' => ['nullable', 'string', 'max:30'],
            'date_accepted' => ['nullable', 'date'],
            'bod_res_date_confirmed' => ['nullable', 'date'],
            'bod_res_number' => ['nullable', 'string', 'max:100'],
            'membership_type' => ['nullable', $opt('membership_type')],
            'membership_kind' => ['nullable', $opt('membership_kind')],
            'migs_status' => ['nullable', $opt('migs_status')],
            'activity_status' => ['nullable', $opt('activity_status')],
            'initial_shares' => ['nullable', 'integer', 'min:0'],
            'initial_share_amount' => ['nullable', 'numeric', 'min:0'],
            'initial_paid_up' => ['nullable', 'numeric', 'min:0'],
            'present_address' => ['nullable', 'string', 'max:500'],
            'permanent_address' => ['nullable', 'string', 'max:500'],
            'business_address' => ['nullable', 'string', 'max:500'],
            'sex_assigned_at_birth' => ['nullable', $opt('sex')],
            'gender_identity' => ['nullable', $opt('gender_identity')],
            'civil_status' => ['nullable', $opt('civil_status')],
            'education_attainment' => ['nullable', $opt('education_attainment')],
            'occupation_category' => ['nullable', $opt('occupation_category')],
            'actual_occupation' => ['nullable', 'string', 'max:150'],
            'occupation_status' => ['nullable', $opt('occupation_status')],
            'industry' => ['nullable', $opt('industry')],
            'number_of_dependents' => ['nullable', 'integer', 'min:0', 'max:50'],
            'religion' => ['nullable', $opt('religion')],
            'annual_income' => ['nullable', 'numeric', 'min:0'],
            'termination_bod_res' => ['nullable', 'string', 'max:100'],
            'termination_date' => ['nullable', 'date'],
            'ethnicity' => ['nullable', $opt('ethnicity')],
            'is_pwd' => ['nullable', 'boolean'],
            'disability' => ['nullable', 'string', 'max:150'],
            'contact_number' => ['nullable', 'string', 'max:30'],
            'email_address' => ['nullable', 'email', 'max:150'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        // share capital / savings balances are fed by the extract now — not editable here.

        $data['is_pwd'] = $request->filled('is_pwd') ? (bool) $request->boolean('is_pwd') : null;

        $member->fill($data);

        // Any of Type / Kind / MIGS / Active that the user set to something other
        // than the rule's result becomes "locked" and is left alone on re-import.
        MemberClassifier::reconcileLocks($member);

        $member->recomputeCompletion();
        $member->save();

        return redirect()
            ->route('members.edit', $member)
            ->with('success', "Saved. {$member->fullName()} is now “{$member->completion_status}”.");
    }
}
