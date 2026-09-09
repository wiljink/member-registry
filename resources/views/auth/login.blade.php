<x-guest-layout>
    <style>
        .lg h2{margin:0 0 4px;font-size:1.3rem;font-weight:800;letter-spacing:-.01em;text-align:center;}
        .lg .sub{margin:0 0 22px;color:var(--mr-muted);font-size:.88rem;text-align:center;}

        .lg-alert{display:flex;gap:9px;align-items:flex-start;background:var(--mr-err-bg);color:var(--mr-err);
            border-radius:10px;padding:10px 13px;font-size:.83rem;font-weight:600;margin-bottom:16px;line-height:1.4;}
        .lg-alert svg{flex:none;margin-top:1px;}
        .lg-status{background:var(--mr-ok-bg);color:var(--mr-ok);border-radius:10px;padding:10px 13px;
            font-size:.83rem;font-weight:600;margin-bottom:16px;}

        .lg-field{margin-bottom:15px;}
        .lg-field label{display:block;font-size:.73rem;font-weight:700;text-transform:uppercase;
            letter-spacing:.04em;color:#475569;margin-bottom:6px;}
        .lg-input{position:relative;display:flex;align-items:center;}
        .lg-input svg.ico{position:absolute;left:12px;color:#94a3b8;pointer-events:none;}
        .lg-input input{width:100%;padding:11px 12px 11px 38px;border:1px solid #cbd5e1;border-radius:10px;
            font:inherit;font-size:.92rem;background:#fff;transition:border-color .15s,box-shadow .15s;}
        .lg-input input:focus{outline:0;border-color:#93c5fd;box-shadow:0 0 0 3px rgba(59,130,246,.18);}
        .lg-input.invalid input{border-color:#fca5a5;box-shadow:0 0 0 3px rgba(248,113,113,.15);}
        .lg-input .toggle{position:absolute;right:6px;background:none;border:0;cursor:pointer;color:#64748b;
            padding:7px;border-radius:8px;display:grid;place-items:center;}
        .lg-input .toggle:hover{background:#f1f5f9;color:#334155;}
        .lg-err{margin-top:5px;font-size:.76rem;color:var(--mr-err);font-weight:600;}
        .lg-caps{margin-top:7px;font-size:.74rem;color:var(--mr-err);font-weight:600;align-items:center;gap:6px;display:none;}
        .lg-caps.show{display:flex;}

        .lg-row{display:flex;align-items:center;justify-content:space-between;margin:2px 0 20px;gap:10px;flex-wrap:wrap;}
        .lg-check{display:flex;align-items:center;gap:8px;font-size:.83rem;color:var(--mr-muted);cursor:pointer;user-select:none;}
        .lg-check input{width:16px;height:16px;accent-color:var(--mr-primary);cursor:pointer;}
        .lg-link{font-size:.83rem;font-weight:600;color:var(--mr-primary);}
        .lg-link:hover{text-decoration:underline;}

        .lg-btn{width:100%;border:0;cursor:pointer;font:inherit;font-weight:700;font-size:.95rem;color:#fff;
            background:var(--mr-primary);padding:12px 16px;border-radius:11px;display:flex;align-items:center;
            justify-content:center;gap:9px;transition:background .15s,transform .05s;}
        .lg-btn:hover{background:var(--mr-primary-dark);}
        .lg-btn:active{transform:translateY(1px);}
        .lg-btn[disabled]{opacity:.75;cursor:progress;}
        .lg-btn .spin{width:15px;height:15px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;
            border-radius:50%;animation:lg-spin .6s linear infinite;display:none;}
        .lg-btn.loading .spin{display:block;}
        @keyframes lg-spin{to{transform:rotate(360deg);}}

        .lg-hint{margin-top:20px;text-align:center;font-size:.78rem;color:var(--mr-muted);line-height:1.5;}
    </style>

    <div class="lg">
        <h2>Welcome back</h2>
        <p class="sub">Sign in to continue to the registry.</p>

        @if (session('status'))
            <div class="lg-status">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="lg-alert" role="alert">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" id="loginForm" novalidate>
            @csrf

            <div class="lg-field">
                <label for="email">Email address</label>
                <div class="lg-input @error('email') invalid @enderror">
                    <svg class="ico" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/></svg>
                    <input id="email" type="email" name="email" value="{{ old('email') }}"
                           required autofocus autocomplete="username" inputmode="email"
                           placeholder="you@orointegrated.coop">
                </div>
                @error('email')<div class="lg-err">{{ $message }}</div>@enderror
            </div>

            <div class="lg-field">
                <label for="password">Password</label>
                <div class="lg-input @error('password') invalid @enderror">
                    <svg class="ico" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    <input id="password" type="password" name="password" required
                           autocomplete="current-password" placeholder="Enter your password">
                    <button type="button" class="toggle" id="pwToggle" aria-label="Show password" title="Show password">
                        <svg id="pwEye" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
                @error('password')<div class="lg-err">{{ $message }}</div>@enderror
                <div class="lg-caps" id="capsWarn">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 2 2 12h4v8h12v-8h4L12 2Z"/></svg>
                    Caps Lock is on
                </div>
            </div>

            <div class="lg-row">
                <label class="lg-check">
                    <input type="checkbox" name="remember" id="remember_me">
                    Keep me signed in
                </label>
                @if (Route::has('password.request'))
                    <a class="lg-link" href="{{ route('password.request') }}">Forgot password?</a>
                @endif
            </div>

            <button type="submit" class="lg-btn" id="loginBtn">
                <span class="spin"></span>
                <span class="label">Sign in</span>
            </button>
        </form>

        <div class="lg-hint">Need an account or a password reset? Contact your administrator.</div>
    </div>

    <script>
        (function () {
            var form   = document.getElementById('loginForm');
            var btn    = document.getElementById('loginBtn');
            var pw     = document.getElementById('password');
            var toggle = document.getElementById('pwToggle');
            var eye    = document.getElementById('pwEye');
            var caps   = document.getElementById('capsWarn');

            toggle.addEventListener('click', function () {
                var show = pw.type === 'password';
                pw.type = show ? 'text' : 'password';
                toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                toggle.title = show ? 'Hide password' : 'Show password';
                eye.innerHTML = show
                    ? '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-6.5 0-10-7-10-7a18.5 18.5 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c6.5 0 10 7 10 7a18.5 18.5 0 0 1-2.16 3.19M1 1l22 22"/>'
                    : '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>';
                pw.focus();
            });

            function capsCheck(e) {
                if (e.getModifierState && e.getModifierState('CapsLock')) caps.classList.add('show');
                else caps.classList.remove('show');
            }
            pw.addEventListener('keydown', capsCheck);
            pw.addEventListener('keyup', capsCheck);
            pw.addEventListener('blur', function () { caps.classList.remove('show'); });

            form.addEventListener('submit', function () {
                btn.classList.add('loading');
                btn.disabled = true;
                btn.querySelector('.label').textContent = 'Signing in…';
            });
        })();
    </script>
</x-guest-layout>
