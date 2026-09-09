<x-app-layout>
    <style>
        .mr-toolbar{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:16px;}
        .mr-toolbar .mr-input,.mr-toolbar .mr-select{width:auto;min-width:150px;}
        .mr-toolbar .grow{flex:1;min-width:220px;}
        .mr-pag{padding:14px;border-top:1px solid #f1f5f9;}
        .mr-pag nav{justify-content:center;}
    </style>

    <h1 class="mr-page-title">Members</h1>
    <p class="mr-page-sub">{{ number_format($members->total()) }} records. Click a row to fill in the missing registry information.</p>

    <form method="GET" class="mr-toolbar">
        <input type="text" name="q" value="{{ request('q') }}" class="mr-input grow" placeholder="Search name, CID or NID…">
        <select name="branch" class="mr-select">
            <option value="">All branches</option>
            @foreach ($branches as $name)
                <option value="{{ $name }}" @selected(request('branch') === $name)>{{ $name }}</option>
            @endforeach
        </select>
        @if ($periods->isNotEmpty())
            <select name="period" class="mr-select">
                <option value="">Any month</option>
                @foreach ($periods as $p)
                    <option value="{{ $p }}" @selected(request('period') === $p)>{{ \Illuminate\Support\Carbon::parse($p.'-01')->format('M Y') }}</option>
                @endforeach
            </select>
        @endif
        <select name="status" class="mr-select">
            <option value="">Any completion</option>
            @foreach (['pending' => 'Pending', 'in_progress' => 'In progress', 'complete' => 'Complete'] as $v => $l)
                <option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>
            @endforeach
        </select>
        <select name="loans" class="mr-select">
            <option value="">All members</option>
            <option value="with" @selected(request('loans') === 'with')>With loans</option>
            <option value="delinquent" @selected(request('loans') === 'delinquent')>Delinquent</option>
        </select>
        <button class="mr-btn" type="submit">Filter</button>
        @if (request()->hasAny(['q','branch','period','status','loans']))
            <a class="mr-btn ghost" href="{{ route('members.index') }}">Reset</a>
        @endif
    </form>

    <div class="mr-card">
        <table class="mr-table">
            <thead>
                <tr>
                    <th>CID</th><th>Name</th><th>Sex</th><th>Age</th><th>Civil status</th>
                    <th>Contact</th><th style="text-align:right;">Loans</th><th style="text-align:right;">Outstanding</th>
                    <th>Completion</th><th></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($members as $m)
                <tr>
                    <td class="mr-muted">{{ $m->cid }}</td>
                    <td><strong>{{ $m->fullName() ?: '—' }}</strong></td>
                    <td>{{ $m->sex_assigned_at_birth ?: '—' }}</td>
                    <td>{{ $m->computedAge() ?? '—' }}</td>
                    <td>{{ $m->civil_status ?: '—' }}</td>
                    <td class="mr-muted">{{ $m->contact_number ?: '—' }}</td>
                    <td style="text-align:right;">{{ $m->loan_count ?: '' }}</td>
                    <td style="text-align:right;">{{ $m->loan_count ? number_format($m->total_outstanding, 2) : '' }}</td>
                    <td><span class="mr-badge {{ $m->completion_status }}">{{ str_replace('_',' ',$m->completion_status) }}</span></td>
                    <td style="text-align:right;"><a href="{{ route('members.edit', $m) }}">Edit</a></td>
                </tr>
            @empty
                <tr><td colspan="10" class="mr-muted" style="text-align:center;padding:30px;">No members match. Import the CIC Members file from <a href="{{ route('imports.index') }}">Imports</a>.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="mr-pag">{{ $members->links() }}</div>
    </div>
</x-app-layout>
