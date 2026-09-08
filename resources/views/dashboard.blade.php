<x-app-layout>
    <style>
        .mr-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:24px;}
        .mr-stat{padding:16px 18px;}
        .mr-stat .n{font-size:1.7rem;font-weight:800;line-height:1;}
        .mr-stat .l{color:var(--mr-muted);font-size:.78rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;margin-top:6px;}
        .mr-grid2{display:grid;grid-template-columns:1.3fr 1fr;gap:20px;align-items:start;}
        @media(max-width:900px){.mr-grid2{grid-template-columns:1fr;}}
        .mr-sec-h{font-weight:800;margin:0 0 12px;font-size:1rem;}
    </style>

    <h1 class="mr-page-title">Dashboard</h1>
    <p class="mr-page-sub">Registry completion at a glance.</p>

    <div class="mr-stats">
        <div class="mr-card mr-stat"><div class="n">{{ number_format($stats['total']) }}</div><div class="l">Members</div></div>
        <div class="mr-card mr-stat"><div class="n" style="color:var(--mr-ok)">{{ number_format($stats['complete']) }}</div><div class="l">Complete</div></div>
        <div class="mr-card mr-stat"><div class="n" style="color:var(--mr-warn)">{{ number_format($stats['in_progress']) }}</div><div class="l">In progress</div></div>
        <div class="mr-card mr-stat"><div class="n" style="color:#475569">{{ number_format($stats['pending']) }}</div><div class="l">Pending</div></div>
        <div class="mr-card mr-stat"><div class="n">{{ number_format($stats['with_loans']) }}</div><div class="l">With loans</div></div>
        <div class="mr-card mr-stat"><div class="n" style="color:var(--mr-err)">{{ number_format($stats['delinquent']) }}</div><div class="l">Delinquent</div></div>
    </div>

    <div class="mr-grid2">
        <div class="mr-card" style="padding:18px;">
            <div class="mr-sec-h">Recent imports</div>
            <table class="mr-table">
                <thead><tr><th>When</th><th>Type</th><th>File</th><th>Created</th><th>Updated</th><th>Skipped</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($batches as $b)
                    <tr>
                        <td class="mr-muted">{{ $b->created_at->format('Y-m-d H:i') }}</td>
                        <td>{{ ucfirst($b->type) }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($b->original_filename, 28) }}</td>
                        <td>{{ number_format($b->rows_created) }}</td>
                        <td>{{ number_format($b->rows_updated) }}</td>
                        <td>{{ number_format($b->rows_skipped) }}</td>
                        <td>
                            <span class="mr-badge {{ $b->status === 'completed' ? 'complete' : ($b->status === 'failed' ? 'pending' : 'in_progress') }}">{{ $b->status }}</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="mr-muted" style="text-align:center;padding:24px;">No imports yet — <a href="{{ route('imports.index') }}">upload a file</a>.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mr-card" style="padding:18px;">
            <div class="mr-sec-h">Sex distribution (assigned at birth)</div>
            <table class="mr-table">
                <tbody>
                    <tr><td>Male</td><td style="text-align:right;font-weight:700;">{{ number_format($stats['male']) }}</td></tr>
                    <tr><td>Female</td><td style="text-align:right;font-weight:700;">{{ number_format($stats['female']) }}</td></tr>
                    <tr><td class="mr-muted">Unspecified</td><td style="text-align:right;" class="mr-muted">{{ number_format($stats['total'] - $stats['male'] - $stats['female']) }}</td></tr>
                </tbody>
            </table>
            <div style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap;">
                <a class="mr-btn" href="{{ route('exports.registry') }}">Export Registry (.xlsx)</a>
                <a class="mr-btn ghost" href="{{ route('exports.gad') }}">Export GAD report</a>
            </div>
        </div>
    </div>
</x-app-layout>
