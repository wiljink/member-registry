<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign in · {{ config('app.name', 'Member Registry') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root{
            --mr-bg:#eef2f7; --mr-card:#ffffff; --mr-ink:#1e293b; --mr-muted:#64748b;
            --mr-border:#e2e8f0; --mr-primary:#1d4ed8; --mr-primary-dark:#1e3a8a;
            --mr-ok:#15803d; --mr-ok-bg:#dcfce7; --mr-err:#b91c1c; --mr-err-bg:#fee2e2;
        }
        *,*::before,*::after{box-sizing:border-box;}
        html,body{margin:0;padding:0;min-height:100%;}
        body{
            font-family:Figtree,ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
            color:var(--mr-ink);
            background:
                radial-gradient(1100px 560px at 6% -12%, rgba(37,99,235,.20), transparent 60%),
                radial-gradient(950px 680px at 112% 118%, rgba(30,58,138,.22), transparent 55%),
                var(--mr-bg);
            min-height:100vh;
            display:flex; align-items:center; justify-content:center;
            padding:32px 18px;
        }

        .guest-card{
            width:100%; max-width:420px;
            background:var(--mr-card);
            border-radius:18px;
            box-shadow:0 24px 60px -22px rgba(15,23,42,.35), 0 6px 20px -12px rgba(15,23,42,.18);
            overflow:hidden;
        }
        .guest-card > .gc-accent{height:5px;background:linear-gradient(90deg,var(--mr-primary-dark),var(--mr-primary),#3b82f6);}
        .guest-card > .gc-body{padding:34px 34px 30px;}
        @media(max-width:440px){.guest-card > .gc-body{padding:26px 22px 24px;}}

        .gc-brand{display:flex;align-items:center;justify-content:center;gap:10px;font-weight:800;
            font-size:1.02rem;color:var(--mr-ink);margin-bottom:22px;}
        .gc-brand .mark{width:34px;height:34px;flex:none;display:grid;place-items:center;}
        .gc-brand .mark img{width:100%;height:100%;object-fit:contain;}
    </style>
</head>
<body>
    <div class="guest-card">
        <div class="gc-accent"></div>
        <div class="gc-body">
            <div class="gc-brand">
                <span class="mark">
                    <img src="{{ asset('images/coop-logo.png') }}" alt="Cooperative logo">
                </span>
                {{ config('app.name', 'Member Registry') }}
            </div>

            {{ $slot }}
        </div>
    </div>
</body>
</html>
