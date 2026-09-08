<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Member Registry') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root{
            --mr-bg:#f1f5f9; --mr-card:#ffffff; --mr-ink:#1e293b; --mr-muted:#64748b;
            --mr-border:#e2e8f0; --mr-primary:#1d4ed8; --mr-primary-dark:#1e3a8a;
            --mr-ok:#15803d; --mr-ok-bg:#dcfce7; --mr-warn:#b45309; --mr-warn-bg:#fef3c7;
            --mr-err:#b91c1c; --mr-err-bg:#fee2e2; --mr-nav-h:58px;
        }
        *,*::before,*::after{box-sizing:border-box;}
        html,body{margin:0;padding:0;background:var(--mr-bg);color:var(--mr-ink);
            font-family:Figtree,ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;font-size:14px;}
        a{color:var(--mr-primary);text-decoration:none;}
        .mr-nav{position:sticky;top:0;z-index:50;height:var(--mr-nav-h);display:flex;align-items:center;
            gap:6px;padding:0 22px;background:linear-gradient(90deg,var(--mr-primary-dark),var(--mr-primary));
            box-shadow:0 2px 10px rgba(30,58,138,.25);}
        .mr-brand{color:#fff;font-weight:800;letter-spacing:.02em;margin-right:18px;white-space:nowrap;}
        .mr-nav a.mr-link{display:inline-flex;align-items:center;gap:6px;padding:7px 13px;border-radius:8px;
            color:rgba(255,255,255,.8);font-weight:600;font-size:.85rem;}
        .mr-nav a.mr-link:hover,.mr-nav a.mr-link.active{background:rgba(255,255,255,.15);color:#fff;}
        .mr-nav .mr-right{margin-left:auto;display:flex;align-items:center;gap:10px;}
        .mr-user{color:#fff;font-size:.82rem;opacity:.9;}
        .mr-logout{background:rgba(255,255,255,.14);border:0;color:#fff;font:inherit;font-weight:600;
            padding:6px 12px;border-radius:8px;cursor:pointer;}
        .mr-logout:hover{background:rgba(255,255,255,.25);}
        main{max-width:1400px;margin:0 auto;padding:26px 22px 60px;}
        .mr-flash{margin:0 0 18px;padding:11px 16px;border-radius:10px;font-weight:600;font-size:.9rem;}
        .mr-flash.ok{background:var(--mr-ok-bg);color:var(--mr-ok);}
        .mr-flash.warn{background:var(--mr-warn-bg);color:var(--mr-warn);}
        .mr-flash.err{background:var(--mr-err-bg);color:var(--mr-err);}
        .mr-card{background:var(--mr-card);border:1px solid var(--mr-border);border-radius:12px;}
        .mr-page-title{font-size:1.35rem;font-weight:800;margin:0 0 4px;}
        .mr-page-sub{color:var(--mr-muted);margin:0 0 20px;font-size:.9rem;}
        .mr-btn{display:inline-flex;align-items:center;gap:7px;background:var(--mr-primary);color:#fff;
            font-weight:700;font-size:.85rem;padding:9px 16px;border-radius:9px;border:0;cursor:pointer;}
        .mr-btn:hover{background:var(--mr-primary-dark);color:#fff;}
        .mr-btn.ghost{background:#fff;color:var(--mr-ink);border:1px solid var(--mr-border);}
        .mr-btn.ghost:hover{background:#f8fafc;}
        .mr-badge{display:inline-block;padding:3px 9px;border-radius:999px;font-size:.7rem;font-weight:800;
            text-transform:uppercase;letter-spacing:.03em;}
        .mr-badge.complete{background:var(--mr-ok-bg);color:var(--mr-ok);}
        .mr-badge.in_progress{background:var(--mr-warn-bg);color:var(--mr-warn);}
        .mr-badge.pending{background:#e2e8f0;color:#475569;}
        table.mr-table{width:100%;border-collapse:collapse;font-size:.82rem;}
        table.mr-table thead th{background:#f8fafc;color:var(--mr-muted);text-align:left;font-weight:700;
            font-size:.68rem;letter-spacing:.05em;text-transform:uppercase;padding:10px 12px;border-bottom:1px solid var(--mr-border);}
        table.mr-table tbody td{padding:9px 12px;border-bottom:1px solid #f1f5f9;}
        table.mr-table tbody tr:hover{background:#f8fafc;}
        .mr-input,.mr-select,textarea.mr-input{width:100%;padding:8px 11px;border:1px solid #cbd5e1;border-radius:8px;
            font:inherit;background:#fff;}
        .mr-input:focus,.mr-select:focus,textarea.mr-input:focus{outline:0;border-color:#93c5fd;
            box-shadow:0 0 0 3px rgba(59,130,246,.15);}
        .mr-input:disabled,.mr-readonly{background:#f1f5f9;color:#64748b;}
        .mr-muted{color:var(--mr-muted);}
    </style>
</head>
<body>
    @include('layouts.navigation')

    <main>
        @foreach (['success' => 'ok', 'warning' => 'warn', 'error' => 'err'] as $key => $cls)
            @if (session($key))
                <div class="mr-flash {{ $cls }}">{{ session($key) }}</div>
            @endif
        @endforeach

        {{ $slot }}
    </main>

    <script>
        document.querySelectorAll('.mr-nav a.mr-link').forEach(a => {
            if (a.href === window.location.href.split('?')[0]) a.classList.add('active');
        });
    </script>
</body>
</html>
