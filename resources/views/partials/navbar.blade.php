{{--
    Navbar utama.

    Menu dihitung dari peran yang sedang login, bukan dari sekadar
    menyembunyikan tautan lewat CSS. Menyembunyikan tautan bukan kontrol
    keamanan: endpoint-nya tetap harus dijaga middleware dan policy
    (Panduan §2). Yang dilakukan di sini hanya mengganti arah dan
    menandai tautan aktif, yaitu hal yang perlu dilihat orang.
--}}
@php
    $user = auth()->user();
    $path = request()->path();
@endphp

<header class="border-bottom bg-white sticky-top no-print" style="border-color: var(--color-border) !important;">
    <nav class="container d-flex align-items-center gap-3" style="height: var(--header-height);" aria-label="Navigasi utama">
        <a href="{{ route('home') }}" class="d-flex align-items-center gap-2 text-decoration-none me-2">
            <img src="{{ asset('images/logo.svg') }}" alt="" width="30" height="30">
            <span class="fw-bold">{{ config('app.name') }}</span>
        </a>

        <button class="btn btn-sm d-lg-none ms-auto border-0"
                data-navbar-toggle aria-expanded="false" aria-controls="menu-utama"
                aria-label="Buka menu navigasi">
            <svg width="22" height="22" aria-hidden="true"><use href="{{ asset('images/icon-sprite.svg') }}#i-search"></use></svg>
        </button>

        <div class="d-none d-lg-flex align-items-center gap-1 flex-grow-1" data-navbar-menu>
            <a href="{{ route('events.index') }}"
               class="btn btn-sm {{ $path === 'kegiatan' || str_starts_with($path, 'kegiatan/') ? 'btn-primary' : 'btn-link text-dark' }}">Kegiatan</a>

            <a href="{{ route('providers.index') }}"
               class="btn btn-sm {{ $path === 'penyelenggara' || str_starts_with($path, 'penyelenggara/') ? 'btn-primary' : 'btn-link text-dark' }}">Penyelenggara</a>

            <a href="{{ route('pages.providerGuide') }}" class="btn btn-sm btn-link text-dark d-none d-xl-inline-block">Panduan</a>

            <form id="filter-katalog" class="ms-auto d-none d-xl-block" style="max-width: 300px;" role="search">
                <label for="nav-cari" class="visually-hidden">Cari kegiatan</label>
                <input type="search" id="nav-cari" name="q" class="form-control form-control-sm"
                       data-search-input placeholder="Cari kegiatan&hellip;" autocomplete="off">
            </form>
        </div>

        <div class="d-none d-lg-flex align-items-center gap-2 ms-auto">
            @guest
                <a href="{{ route('auth.login') }}" class="btn btn-sm btn-outline-primary">Masuk</a>
                <a href="{{ route('auth.register') }}" class="btn btn-sm btn-primary">Daftar</a>
            @endguest

            @auth
                @if ($user->isProvider())
                    <a href="{{ route('provider.dashboard') }}"
                       class="btn btn-sm {{ str_starts_with($path, 'dasbor/penyelenggara') ? 'btn-primary' : 'btn-outline-primary' }}">Kelola Kegiatan</a>
                @elseif ($user->isAdmin())
                    <a href="{{ route('admin.dashboard') }}" class="btn btn-sm btn-outline-primary">Panel Admin</a>
                @else
                    <a href="{{ route('user.dashboard') }}" class="btn btn-sm btn-outline-primary">Dasbor</a>
                @endif

                <div class="position-relative" data-dropdown>
                    <button class="btn btn-sm btn-link text-dark d-flex align-items-center gap-2"
                            data-dropdown-trigger aria-expanded="false" aria-haspopup="true">
                        <span class="badge-soft badge-soft--neutral">{{ $user->initials() }}</span>
                        <span class="d-none d-xl-inline">{{ $user->name }}</span>
                    </button>
                    <div class="dropdown-panel position-absolute end-0 mt-1 bg-white border rounded p-2 shadow"
                         data-dropdown-panel style="min-width: 220px; z-index: 1000;">
                        <div class="px-2 py-1 small text-muted-soft border-bottom mb-1">
                            {{ $user->email }}
                        </div>
                        <a href="{{ route('user.profile.edit') }}" class="dropdown-item">Profil saya</a>
                        @unless ($user->isAdmin())
                            <a href="{{ route('user.bookmarks.index') }}" class="dropdown-item">
                                Tersimpan
                                @if (($navBadges['saved'] ?? 0) > 0)
                                    <span class="counter ms-1">{{ $navBadges['saved'] }}</span>
                                @endif
                            </a>
                        @endunless
                        @if ($user->isProvider())
                            <a href="{{ route('provider.profile.edit') }}" class="dropdown-item">Profil organisasi</a>
                        @endif
                        <form method="POST" action="{{ route('auth.logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item text-danger">Keluar</button>
                        </form>
                    </div>
                </div>
            @endauth
        </div>
    </nav>
</header>

@if ($navVerificationPending ?? false)
    <div class="alert alert-warning rounded-0 mb-0 py-2 small no-print" role="status">
        <div class="container d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <span>
                <svg width="16" height="16" aria-hidden="true"><use href="{{ asset('images/icon-sprite.svg') }}#i-alert"></use></svg>
                {{ ($navProviderProfile?->verification_status ?? null)?->label() }} — Anda bisa menyimpan draf, tetapi belum bisa mengajukan kegiatan untuk ditinjau.
            </span>
            <a href="{{ route('provider.profile.edit') }}" class="alert-link">Periksa data organisasi</a>
        </div>
    </div>
@endif
