{{--
    Layout panel administrator: sidebar + konten.

    Sidebar memakai <nav> dengan label yang jelas, dan tautan aktif diberi
    aria-current="page" supaya pembaca layar tahu di mana posisinya.
--}}
@php
    $adminNav = [
        ['route' => 'admin.dashboard',    'label' => 'Dasbor',        'icon' => 'i-calendar', 'badge' => 0],
        ['route' => 'admin.events.index', 'label' => 'Tinjau Kegiatan', 'icon' => 'i-shield',  'badge' => $navBadges['reviewQueue'] ?? 0],
        ['route' => 'admin.providers.index', 'label' => 'Verifikasi', 'icon' => 'i-users',    'badge' => 0],
        ['route' => 'admin.reports.index',  'label' => 'Laporan',    'icon' => 'i-alert',     'badge' => $navBadges['reports'] ?? 0],
        ['route' => 'admin.users.index',    'label' => 'Pengguna',   'icon' => 'i-users',     'badge' => 0],
        ['route' => 'admin.categories.index', 'label' => 'Kategori', 'icon' => 'i-check',     'badge' => 0],
        ['route' => 'admin.audit.index',    'label' => 'Jejak Audit', 'icon' => 'i-clock',   'badge' => 0],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Administrator') — {{ config('app.name') }}</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="{{ asset('images/favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="{{ asset('css/tokens.css') }}">
    <link rel="stylesheet" href="{{ asset('css/components.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<a class="skip-link" href="#konten-utama">Lompat ke konten utama</a>

<div class="d-flex">
    <aside class="sidebar no-print">
        <a href="{{ route('admin.dashboard') }}" class="sidebar__brand">
            <img src="{{ asset('images/logo.svg') }}" alt="" width="28" height="28">
            <span>Panel Admin</span>
        </a>

        <nav aria-label="Menu administrator">
            <div class="sidebar__group">
                <p class="sidebar__label">Menu</p>
                @foreach ($adminNav as $item)
                    <a href="{{ route($item['route']) }}"
                       class="sidebar__link {{ request()->routeIs($item['route']) ? 'is-active' : '' }}"
                       @if (request()->routeIs($item['route'])) aria-current="page" @endif>
                        <svg width="18" height="18" aria-hidden="true"><use href="{{ asset('images/icon-sprite.svg') }}#{{ $item['icon'] }}"></use></svg>
                        <span>{{ $item['label'] }}</span>
                        @if ($item['badge'] > 0)
                            <span class="counter sidebar__badge">{{ $item['badge'] }}</span>
                        @endif
                    </a>
                @endforeach
            </div>

            <div class="sidebar__group">
                <p class="sidebar__label">Keluar</p>
                <a href="{{ route('home') }}" class="sidebar__link">
                    <svg width="18" height="18" aria-hidden="true"><use href="{{ asset('images/icon-sprite.svg') }}#i-arrow-right"></use></svg>
                    <span>Lihat situs</span>
                </a>
                <form method="POST" action="{{ route('auth.logout') }}">
                    @csrf
                    <button type="submit" class="sidebar__link border-0 bg-transparent w-100">
                        <svg width="18" height="18" aria-hidden="true"><use href="{{ asset('images/icon-sprite.svg') }}#i-arrow-right"></use></svg>
                        <span>Keluar</span>
                    </button>
                </form>
            </div>
        </nav>
    </aside>

    <main id="konten-utama" class="flex-grow-1">
        <div class="container-fluid px-4 py-4">
            @include('partials.flash')

            <div class="page-head d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <h1 class="page-head__title">@yield('heading', 'Administrator')</h1>
                    <p class="page-head__subtitle">@yield('subheading')</p>
                </div>
                <div class="no-print">@yield('actions')</div>
            </div>

            @yield('content')
        </div>
    </main>
</div>

<div class="toast-stack" data-toast-stack>@include('partials.toast')</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script type="module" src="{{ asset('js/app.js') }}"></script>
@stack('scripts')
</body>
</html>
