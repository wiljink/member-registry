<nav class="mr-nav">
    <span class="mr-brand">Member Registry</span>

    <a class="mr-link" href="{{ route('dashboard') }}">Dashboard</a>
    <a class="mr-link" href="{{ route('members.index') }}">Members</a>
    <a class="mr-link" href="{{ route('imports.index') }}">Imports</a>
    <a class="mr-link" href="{{ route('exports.registry') }}">Export Registry</a>

    <div class="mr-right">
        <span class="mr-user">{{ auth()->user()?->name }}</span>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="mr-logout">Sign out</button>
        </form>
    </div>
</nav>
