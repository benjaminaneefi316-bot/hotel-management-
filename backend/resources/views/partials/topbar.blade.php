<header class="topbar d-flex align-items-center justify-content-between">
    <div>
        <div class="eyebrow">@yield('eyebrow', 'Control centre')</div>
        <h1 class="h4 mb-0">@yield('heading', 'Dashboard')</h1>
    </div>

    <div class="d-flex align-items-center gap-3">
        @hasSection('actions')
            <div class="d-none d-md-block">@yield('actions')</div>
        @endif

        <div class="dropdown">
            <button class="btn btn-soft dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="avatar" style="width:30px;height:30px;font-size:.72rem;">{{ auth()->user()?->initials() }}</span>
                <span class="d-none d-sm-inline">{{ auth()->user()?->name }}</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person me-2"></i>My profile</a></li>
                @if(auth()->user()?->hasRole(\App\Enums\RoleName::Guest))
                    <li><a class="dropdown-item" href="{{ route('guest.dashboard') }}"><i class="bi bi-house-heart me-2"></i>Guest portal</a></li>
                @endif
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="dropdown-item text-danger" type="submit"><i class="bi bi-box-arrow-right me-2"></i>Sign out</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
