@extends('layouts.app')

@section('title', $event->title)
@section('description', \Illuminate\Support\Str::limit(strip_tags($event->description), 155))

@section('content')
<nav aria-label="Remah roti" class="small text-muted-soft mb-3">
    <a href="{{ route('events.index') }}">Kegiatan</a>
    <span aria-hidden="true">/</span>
    <a href="{{ route('events.index', ['category' => $event->category->slug]) }}">{{ $event->category->name }}</a>
</nav>

<div class="row g-4">
    <div class="col-lg-8">
        <article class="card">
            <div class="card__body">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <span class="badge-soft badge-soft--primary">{{ $event->category->name }}</span>
                    <span class="badge-soft badge-soft--neutral">{{ $event->mode->label() }}</span>
                    @include('partials.status-badge', ['event' => $event])
                </div>

                <h1 class="h3 mb-3">{{ $event->title }}</h1>

                <dl class="row mb-4">
                    <dt class="col-sm-4 text-muted-soft fw-normal">Waktu</dt>
                    <dd class="col-sm-8 mb-2">
                        {{ $dateHelper->longDate($event->event_start_at) }}<br>
                        <span class="text-muted-soft">
                            {{ $event->event_start_at->format('H.i') }}&ndash;{{ $event->event_end_at->format('H.i') }} WIB
                        </span>
                    </dd>

                    <dt class="col-sm-4 text-muted-soft fw-normal">Bentuk</dt>
                    <dd class="col-sm-8 mb-2">
                        @if ($event->mode->requiresLocation())
                            {{ $event->location_text }}
                            @if ($event->mode->value === 'hybrid')
                                <span class="text-muted-soft">(hybrid: daring dan luring)</span>
                            @endif
                        @else
                            Daring
                        @endif
                    </dd>

                    <dt class="col-sm-4 text-muted-soft fw-normal">Tenggat pendaftaran</dt>
                    <dd class="col-sm-8 mb-2">
                        @include('partials.deadline-timer', ['event' => $event])
                    </dd>
                </dl>

                <h2 class="h5 mb-2">Tentang kegiatan</h2>
                {{-- Deskripsi disimpan sebagai TEKS, bukan HTML. Karena itu ia
                     dicetak apa adanya, bukan lewat {!! !!}. Menjalankan
                     HTML milik pengguna di dalam situs adalah cara paling
                     umum menimbulkan celah XSS (Panduan §8). --}}
                <div style="white-space: pre-line;">{{ $event->description }}</div>

                @if ($event->requirements)
                    <h2 class="h5 mt-4 mb-2">Syarat peserta</h2>
                    <div style="white-space: pre-line;">{{ $event->requirements }}</div>
                @endif
            </div>
        </article>

        @if ($similar->isNotEmpty())
            <section class="mt-5">
                <h2 class="h5 mb-3">Kegiatan serupa</h2>
                <div class="row g-3">
                    @foreach ($similar as $other)
                        <div class="col-sm-6">
                            @include('partials.event-card', ['event' => $other, 'savedMap' => []])
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>

    <div class="col-lg-4">
        <div class="card sticky-lg-top" style="top: calc(var(--header-height) + 1rem);">
            <div class="card__body">
                <h2 class="sidebar__label mb-3">Penyelenggara</h2>
                <p class="fw-semibold mb-1">{{ $event->providerProfile->organization_name }}</p>
                <p class="small text-muted-soft mb-2">{{ $event->providerProfile->campus_name }}</p>

                @if ($event->providerProfile->isVerified())
                    <p class="badge-soft badge-soft--success mb-3">
                        <svg width="13" height="13" aria-hidden="true"><use href="{{ asset('images/icon-sprite.svg') }}#i-check"></use></svg>
                        Terverifikasi
                    </p>
                @endif

                <a href="{{ route('providers.show', $event->providerProfile->routeSlug()) }}" class="btn btn-outline-secondary btn-sm w-100 mb-3">
                    Lihat profil penyelenggara
                </a>

                <hr>

                <p class="sidebar__label mb-2">Pendaftaran</p>
                <p class="small text-muted-soft mb-3">
                    Pendaftaran dilakukan di situs penyelenggara, bukan di sini.
                </p>

                <a href="{{ $event->registration_url }}" target="_blank" rel="noopener noreferrer nofollow"
                   class="btn btn-primary w-100 mb-2">
                    Buka halaman pendaftaran
                    <span class="visually-hidden">(membuka situs baru)</span>
                </a>

                @auth
                    <button type="button"
                            class="btn btn-outline-primary w-100 mb-2"
                            data-bookmark-url="{{ route('events.bookmark.store', $event) }}"
                            data-saved="{{ $saved ? '1' : '0' }}"
                            aria-pressed="{{ $saved ? 'true' : 'false' }}">
                        <svg width="16" height="16" aria-hidden="true" data-bookmark-icon fill="{{ $saved ? 'currentColor' : 'none' }}">
                            <use href="{{ asset('images/icon-sprite.svg') }}#i-bookmark"></use>
                        </svg>
                        <span data-bookmark-label>{{ $saved ? 'Tersimpan' : 'Simpan' }}</span>
                        <span class="counter ms-1" data-bookmark-counter>{{ $event->savedCount() }}</span>
                    </button>

                    @if (auth()->user()->can('report', $event))
                        <a href="{{ route('user.reports.create', $event) }}" class="btn btn-link btn-sm w-100">
                            Ada yang salah? Laporkan
                        </a>
                    @endif
                @else
                    <a href="{{ route('auth.login') }}" class="btn btn-outline-primary w-100">
                        Masuk untuk menyimpan
                    </a>
                @endauth
            </div>
        </div>
    </div>
</div>
@endsection
