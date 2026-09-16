<x-app-layout>
    <style>
        .mr-up{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:26px;}
        @media(max-width:800px){.mr-up{grid-template-columns:1fr;}}
        .mr-up .mr-card{padding:18px;}
        .mr-up h3{margin:0 0 4px;font-size:1rem;}
        .mr-up p{margin:0 0 14px;color:var(--mr-muted);font-size:.83rem;}
        .mr-up label{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.03em;color:#475569;display:block;}
        .mr-up input[type=file]{width:100%;font-size:.83rem;margin:4px 0 12px;}
        .mr-up input[type=month]{padding:7px 10px;border:1px solid #cbd5e1;border-radius:8px;font:inherit;margin:4px 0 12px;}
        .mr-errs{margin-top:4px;font-size:.76rem;color:var(--mr-err);}
        .mr-del{background:none;border:0;color:var(--mr-err);font-weight:700;font-size:.78rem;cursor:pointer;padding:4px 7px;border-radius:6px;}
        .mr-del:hover{background:var(--mr-err-bg);}
    </style>

    @php $thisMonth = now()->format('Y-m'); @endphp

    <h1 class="mr-page-title">Imports</h1>
    <p class="mr-page-sub">
        Run <code>database/sql/cic_merged_registry_extract.sql</code> in SSMS, export result&nbsp;1
        (“Members”) and result&nbsp;2 (“Loans”) to Excel/CSV, then upload them here — <strong>one
        file each, all branches together</strong>. Pick the <strong>data month</strong> the
        export represents; every row is tagged with it, and filed under its own
        <code>Branch Code</code> value. Re-importing refreshes the CIC source columns only;
        hand-keyed values are kept.
    </p>

    <div class="mr-up">
        <div class="mr-card">
            <h3>1 · Members file</h3>
            <p>One row per member (CIC “ID” subject data). Upserted by CID.</p>
            <form method="POST" action="{{ route('imports.members') }}" enctype="multipart/form-data">
                @csrf
                <label>Data month</label>
                <input type="month" name="period" value="{{ old('period', $thisMonth) }}" max="{{ $thisMonth }}" required>
                @error('period')<div class="mr-errs">{{ $message }}</div>@enderror
                <label>File</label>
                <input type="file" name="file" accept=".xlsx,.xls,.csv" required>
                <button class="mr-btn" type="submit">Upload members</button>
                @error('file')<div class="mr-errs">{{ $message }}</div>@enderror
            </form>
        </div>

        <div class="mr-card">
            <h3>2 · Loans file</h3>
            <p>One row per loan account (CIC “CI” contract data). Feeds the loan-portfolio summary.</p>
            <form method="POST" action="{{ route('imports.loans') }}" enctype="multipart/form-data">
                @csrf
                <label>Data month</label>
                <input type="month" name="period" value="{{ old('period', $thisMonth) }}" max="{{ $thisMonth }}" required>
                <label>File</label>
                <input type="file" name="file" accept=".xlsx,.xls,.csv" required>
                <button class="mr-btn" type="submit">Upload loans</button>
            </form>
        </div>
    </div>

    <div class="mr-card" style="padding:18px;">
        <div style="font-weight:800;margin-bottom:12px;">Import history</div>
        <table class="mr-table">
            <thead><tr><th>When</th><th>By</th><th>Month</th><th>Branch(es)</th><th>Type</th><th>File</th><th>Total</th><th>Created</th><th>Updated</th><th>Skipped</th><th>Status</th><th>Errors</th><th></th></tr></thead>
            <tbody>
            @forelse ($batches as $b)
                <tr>
                    <td class="mr-muted">{{ $b->created_at->format('Y-m-d H:i') }}</td>
                    <td>{{ $b->user?->name ?? '—' }}</td>
                    <td>{{ $b->period?->format('M Y') ?? '—' }}</td>
                    <td>{{ $b->branch ?? '—' }}</td>
                    <td>{{ ucfirst($b->type) }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($b->original_filename, 30) }}</td>
                    <td>{{ number_format($b->rows_total) }}</td>
                    <td>{{ number_format($b->rows_created) }}</td>
                    <td>{{ number_format($b->rows_updated) }}</td>
                    <td>{{ number_format($b->rows_skipped) }}</td>
                    <td><span class="mr-badge {{ $b->status === 'completed' ? 'complete' : ($b->status === 'failed' ? 'pending' : 'in_progress') }}">{{ $b->status }}</span></td>
                    <td class="mr-muted" style="max-width:260px;">{{ $b->errors ? \Illuminate\Support\Str::limit(implode(' | ', $b->errors), 90) : '' }}</td>
                    <td>
                        <form method="POST" action="{{ route('imports.destroy', $b) }}"
                              onsubmit="return confirm('Delete this import-history entry?\n\nThe members and loans it imported stay exactly as they are — only the log line is removed.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="mr-del">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="13" class="mr-muted" style="text-align:center;padding:24px;">No imports yet.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div style="padding-top:12px;">{{ $batches->links() }}</div>
    </div>
</x-app-layout>
