<x-app-layout>
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
        <h1 class="mr-page-title">{{ $member->fullName() ?: 'Member '.$member->cid }}</h1>
        <a class="mr-btn" href="{{ route('members.edit', $member) }}">Edit</a>
    </div>
    <p class="mr-page-sub">CID {{ $member->cid }} ·
        <span class="mr-badge {{ $member->completion_status }}">{{ str_replace('_',' ',$member->completion_status) }}</span>
    </p>

    <div class="mr-card" style="padding:18px;">
        <table class="mr-table">
            <tbody>
            @foreach ([
                'Name' => $member->fullName(),
                'TIN' => $member->tin,
                'Date accepted' => optional($member->date_accepted)->format('Y-m-d'),
                'Membership' => trim(($member->membership_type ?? '').' / '.($member->membership_kind ?? ''), ' /'),
                'Status' => $member->activity_status,
                'Present address' => $member->present_address,
                'Date of birth' => optional($member->birth_date)->format('Y-m-d'),
                'Age' => $member->computedAge(),
                'Sex / Gender' => trim(($member->sex_assigned_at_birth ?? '').' / '.($member->gender_identity ?? ''), ' /'),
                'Civil status' => $member->civil_status,
                'Education' => $member->education_attainment,
                'Occupation' => trim(($member->occupation_category ?? '').' — '.($member->actual_occupation ?? ''), ' —'),
                'Dependents' => $member->number_of_dependents,
                'Religion' => $member->religion,
                'Contact' => $member->contact_number,
                'Email' => $member->email_address,
                'Share capital' => $member->share_capital_balance,
                'Savings' => $member->savings_balance,
                'Loans' => $member->loan_count.' — outstanding '.number_format((float) $member->total_outstanding, 2),
            ] as $label => $value)
                <tr><th style="width:220px;">{{ $label }}</th><td>{{ filled($value) ? $value : '—' }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
</x-app-layout>
