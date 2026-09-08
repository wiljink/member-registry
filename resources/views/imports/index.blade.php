<x-app-layout>
    <style>
        .mr-up{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:26px;}
        @media(max-width:800px){.mr-up{grid-template-columns:1fr;}}
        .mr-up .mr-card{padding:18px;}
        .mr-up h3{margin:0 0 4px;font-size:1rem;}
        .mr-up p{margin:0 0 14px;color:var(--mr-muted);font-size:.83rem;}
        .mr-up input[type=file]{width:100%;font-size:.83rem;margin-bottom:12px;}
        .mr-errs{margin-top:8px;font-size:.76rem;color:var(--mr-err);}
    </style>

    <h1 class="mr-page-title">Imports</h1>
    <p class="mr-page-sub">
        Run <code>database/sql/cic_merged_registry_extract.sql</code> in SSMS, export result&nbsp;1
        (“Members”) and result&nbsp;2 (“Loans”) to Excel/CSV, then upload them here.
        Re-importing refreshes the CIC source columns only — hand-keyed values are kept.
    </p>

    <div class="mr-up">
        <div class="mr-card">
            <h3>1 · Members file</h3>
            <p>One row per member (CIC “ID” subject data). Upserted by CID.</p>
            <form method="POST" action="{{ route('imports.members') }}" enctype="multipart/form-data">
                @csrf
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
                <input type="file" name="file" accept=".xlsx,.xls,.csv" required>
                <button class="mr-btn" type="submit">Upload loans</button>
            </form>
        </div>
    </div>

    <div class="mr-card" style="padding:18px;">
        <div style="font-weight:800;margin-bottom:12px;">Import history</div>
        <table class="mr-table">
            <thead><tr><th>When</th><th>By</th><th>Type</th><th>File</th><th>Total</th><th>Created</th><th>Updated</th><th>Skipped</th><th>Status</th><th>Errors</th></tr></thead>
            <tbody>
            @forelse ($batches as $b)
                <tr>
                    <td class="mr-muted">{{ $b->created_at->format('Y-m-d H:i') }}</td>
                    <td>{{ $b->user?->name ?? '—' }}</td>
                    <td>{{ ucfirst($b->type) }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($b->original_filename, 30) }}</td>
                    <td>{{ number_format($b->rows_total) }}</td>
                    <td>{{ number_format($b->rows_created) }}</td>
                    <td>{{ number_format($b->rows_updated) }}</td>
                    <td>{{ number_format($b->rows_skipped) }}</td>
                    <td><span class="mr-badge {{ $b->status === 'completed' ? 'complete' : ($b->status === 'failed' ? 'pending' : 'in_progress') }}">{{ $b->status }}</span></td>
                    <td class="mr-muted" style="max-width:260px;">{{ $b->errors ? \Illuminate\Support\Str::limit(implode(' | ', $b->errors), 90) : '' }}</td>
                </tr>
            @empty
                <tr><td colspan="10" class="mr-muted" style="text-align:center;padding:24px;">No imports yet.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div style="padding-top:12px;">{{ $batches->links() }}</div>
    </div>
</x-app-layout>
