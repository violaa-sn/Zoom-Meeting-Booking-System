@props(['title' => null])
@php
    use Illuminate\Support\Str;
    $user = auth()->user();
    $initials = Str::of($user->name)->explode(' ')->take(2)->map(fn ($w) => Str::upper(Str::substr($w, 0, 1)))->implode('');
    $pending = $user->isAdmin() ? \App\Models\Booking::pending()->count() : 0;
    $nav = [
        ['booking.calendar', 'Kalender', 'calendar', 'booking.calendar', 0],
        ['booking.index', $user->isAdmin() ? 'Semua booking' : 'Booking saya', 'list', 'booking.index', 0],
    ];
    if ($user->isAdmin()) {
        $nav[] = ['approvals.index', 'Persetujuan', 'check', 'approvals.*', $pending];
        $nav[] = ['zoom-accounts.index', 'Akun Zoom', 'video', 'zoom-accounts.*', 0];
        $nav[] = ['users.index', 'Pengguna', 'users', 'users.*', 0];
        $nav[] = ['departments.index', 'Departemen', 'building', 'departments.*', 0];
    }
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' | ' : '' }}Zoom Booking</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <header class="app-header">
        <div class="container-xxl d-flex align-items-center gap-3 py-2 py-md-3">
            <a class="brand" href="{{ route('booking.calendar') }}">
                @include('partials.logo')
                <span class="text-truncate"><b>Zoom Booking</b><small class="d-none d-sm-block">Sistem pemesanan ruang meeting online</small></span>
            </a>
            <div class="ms-auto d-flex align-items-center gap-3">
                <div class="header-date d-none d-xl-flex align-items-center gap-2"><x-icon name="calendar" />{{ now()->locale('id')->translatedFormat('l, j F Y') }}</div>
                <x-notification-bell />
                <div class="dropdown">
                    <button class="btn user-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="avatar">{{ $initials }}</span>
                        <span class="d-none d-md-inline">{{ $user->name }}</span>
                        <x-icon name="chevron-down" :size="16" />
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                        <li class="px-3 py-2"><b class="d-block">{{ $user->name }}</b><small class="text-secondary">{{ '@'.$user->username }}, {{ $user->isAdmin() ? 'Admin' : 'Pengguna' }}</small></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><button type="button" class="dropdown-item d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#password-panel"><x-icon name="key" :size="16" />Ganti password</button></li>
                        <li><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="dropdown-item d-flex align-items-center gap-2"><x-icon name="logout" :size="16" />Keluar</button></form></li>
                    </ul>
                </div>
            </div>
        </div>
    </header>
    <nav class="app-tabs bg-white border-bottom" aria-label="Menu utama">
        <div class="container-xxl">
            <ul class="nav flex-nowrap overflow-x-auto">
                @foreach ($nav as [$route, $label, $icon, $pattern, $count])
                    <li class="nav-item">
                        <a href="{{ route($route) }}" @class(['nav-link', 'active' => request()->routeIs($pattern)]) @if (request()->routeIs($pattern)) aria-current="page" @endif>
                            <x-icon :name="$icon" :size="17" />{{ $label }}
                            @if ($count > 0)<span class="badge rounded-pill text-bg-danger">{{ $count }}</span>@endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </nav>

    <main class="container-xxl py-3 py-md-4">{{ $slot }}</main>

    <x-modal id="password-panel" title="Ganti password" subtitle="Password baru dipakai untuk login berikutnya." :open="old('_form') === 'password-form'">
        <form method="POST" action="{{ route('profile.password') }}">
            @csrf @method('PUT')
            <input type="hidden" name="_form" value="password-form">
            <div class="modal-body d-grid gap-3">
                <x-input label="Password saat ini" name="current_password" type="password" autocomplete="current-password" />
                <x-input label="Password baru" name="password" type="password" hint="Minimal 8 karakter." autocomplete="new-password" />
                <x-input label="Ulangi password baru" name="password_confirmation" type="password" autocomplete="new-password" />
            </div>
            <div class="modal-footer">
                <x-button variant="secondary" data-bs-dismiss="modal">Batal</x-button>
                <x-button type="submit">Simpan password</x-button>
            </div>
        </form>
    </x-modal>

    @include('partials.booking-form')
    {{ $extra ?? '' }}
    <x-flash />
</body>
</html>
