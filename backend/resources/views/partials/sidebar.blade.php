@php
    $user = auth()->user();
    $role = $user?->primaryRole();
@endphp
<aside class="sidebar">
    <div class="p-3 border-bottom border-secondary border-opacity-25">
        <a href="{{ route('dashboard') }}" class="brand d-block">
            Azure&nbsp;Bay
            <span class="text-gold d-block" style="letter-spacing:.28em;font-size:.55rem;">HOTEL SUITE</span>
        </a>
    </div>

    <nav class="nav flex-column p-2">
        <div class="nav-heading">Overview</div>
        <a class="nav-link @active('dashboard')" href="{{ route('dashboard') }}"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a class="nav-link @active('front-desk.*')" href="{{ route('front-desk.index') }}"><i class="bi bi-door-open"></i> Front desk</a>

        @can('manage-bookings')
            <div class="nav-heading">Reservations</div>
            <a class="nav-link @active('bookings.*')" href="{{ route('bookings.index') }}"><i class="bi bi-calendar2-check"></i> Bookings</a>
            <a class="nav-link @active('guests.*')" href="{{ route('guests.index') }}"><i class="bi bi-people"></i> Guests</a>
        @endcan

        @can('manage-payments')
            <a class="nav-link @active('payments.*')" href="{{ route('payments.index') }}"><i class="bi bi-credit-card"></i> Payments</a>
        @endcan

        @canany(['manage-rooms', 'manage-room-types'])
            <div class="nav-heading">Property</div>
            @can('manage-rooms')
                <a class="nav-link @active('rooms.*')" href="{{ route('rooms.index') }}"><i class="bi bi-building"></i> Rooms</a>
            @endcan
            @can('manage-room-types')
                <a class="nav-link @active('room-types.*')" href="{{ route('room-types.index') }}"><i class="bi bi-grid-1x2"></i> Room types</a>
            @endcan
        @endcanany

        @canany(['manage-users', 'manage-roles', 'view-archived-users'])
            <div class="nav-heading">Administration</div>
            @can('manage-users')
                <a class="nav-link @active('users.*')" href="{{ route('users.index') }}"><i class="bi bi-person-badge"></i> Users</a>
            @endcan
            @can('view-archived-users')
                <a class="nav-link @active('archived-users.*')" href="{{ route('archived-users.index') }}"><i class="bi bi-archive"></i> Archived users</a>
            @endcan
            @can('manage-roles')
                <a class="nav-link @active('roles.*')" href="{{ route('roles.index') }}"><i class="bi bi-shield-lock"></i> Roles</a>
            @endcan
        @endcanany

        @canany(['view-reports', 'manage-settings', 'manage-backups', 'manage-restore-points', 'view-audit-logs'])
            <div class="nav-heading">System</div>
            @can('view-reports')
                <a class="nav-link @active('reports.*')" href="{{ route('reports.index') }}"><i class="bi bi-graph-up"></i> Reports</a>
            @endcan
            @can('manage-settings')
                <a class="nav-link @active('settings.*')" href="{{ route('settings.index') }}"><i class="bi bi-sliders"></i> Settings</a>
            @endcan
            @can('manage-restore-points')
                <a class="nav-link @active('restore-points.*')" href="{{ route('restore-points.index') }}"><i class="bi bi-clock-history"></i> Restore points</a>
            @endcan
            @can('manage-backups')
                <a class="nav-link @active('backups.*')" href="{{ route('backups.index') }}"><i class="bi bi-hdd-stack"></i> Backups</a>
            @endcan
            @can('view-audit-logs')
                <a class="nav-link @active('audit-logs.*')" href="{{ route('audit-logs.index') }}"><i class="bi bi-journal-text"></i> Audit logs</a>
            @endcan
        @endcanany

        <div class="nav-heading">Account</div>
        <a class="nav-link @active('profile.*')" href="{{ route('profile.edit') }}"><i class="bi bi-person-gear"></i> My profile</a>
        <a class="nav-link" href="{{ route('public.home') }}"><i class="bi bi-globe2"></i> Public site</a>
    </nav>

    <div class="p-3 mt-2 border-top border-secondary border-opacity-25">
        <div class="d-flex align-items-center gap-2">
            <span class="avatar">{{ $user?->initials() }}</span>
            <div class="small lh-sm">
                <div class="text-white">{{ $user?->name }}</div>
                <div class="text-gold" style="font-size:.72rem;">{{ $role?->label() ?? 'No role' }}</div>
            </div>
        </div>
    </div>
</aside>
