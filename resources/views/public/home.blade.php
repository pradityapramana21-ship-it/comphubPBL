@extends('layouts.app', ['title' => 'Beranda'])

@section('content')
@php
    $savedMap = auth()->check()
        ? app(\App\Services\BookmarkService::class)
            ->savedMap(auth()->user(), $terdekat->pluck('id')->merge($terbaru->pluck('id'))->unique()->all())
        : [];
@endphp

<section class="mb-5">
    <div class="row align-items-center g-4">
        <div class="col-lg-7">
            <span class="badge-soft badge-soft--primary mb-3">Katalog kegiatan mahasiswa</span>
            <h1 class="mb-3">Temukan lomba, webinar, dan seminar dari penyelenggara terverifikasi.</h1>
            <p class="lead text-muted-soft mb-4" style="max-width: 52ch;">
                Setiap kegiatan di sini sudah melewati pemeriksaan administrator.
                Anda tidak akan menemukan kegiatan yang sudah kedaluwarsa
                atau tautan pendaftaran yang rusak.
            </p>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('events.index') }}" class="btn btn-primary">
                    Jelajahi semua kegiatan
                    <svg width="16" height="16" aria-hidden="true" class="ms-1"><use href="{{ asset('images/icon-sprite.svg') }}#i-arrow-right"></use></svg>
                </a>
                <a href="{{ route('auth.registerProvider') }}" class="btn btn-outline-primary">Daftar sebagai penyelenggara</a>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="row g-3">
                <div class="col-6">
                    <div class="stat">
                        <p class="stat__label">Total kegiatan</p>
                        <p class="stat__value">{{ number_format($totalKegiatan, 0, ',', '.') }}</p>
                    </div>
                </div>
                <div class="col-6">
                    <div class="stat stat--info">
                        <p class="stat__label">Penyelenggara</p>
                        <p class="stat__value">{{ number_format($totalPenyedia, 0, ',', '.') }}</p>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card">
                        <div class="card__body">
                            <p class="sidebar__label mb-2">Cara kerja di sini</p>
                            <ol class="mb-0 ps-3 small text-muted-soft">
                                <li class="mb-1">Penyelenggara mengajukan kegiatan.</li>
                                <li class="mb-1">Pemeriksaan otomatis menyaring penyalahgunaan.</li>
                                <li class="mb-1">Administrator meninjau dan memutuskan.</li>
                                <li>Hanya yang disetujui yang muncul di katalog.</li>
                            </ol>
                            <a href="{{ route('pages.moderation') }}" class="small d-inline-block mt-2">Baca kebijakan moderasi</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@if ($navCategories->isNotEmpty())
    <section class="mb-5">
        <h2 class="h5 mb-3">Jelajahi per kategori</h2>
        <div class="d-flex flex-wrap gap-2">
            @foreach ($navCategories as $category)
                <a href="{{ route('events.index', ['category' => $category->slug]) }}"
                   class="btn btn-outline-secondary btn-sm">
                    {{ $category->name }}
                </a>
            @endforeach
        </div>
    </section>
@endif

@if ($terdekat->isNotEmpty())
    <section class="mb-5">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h5 mb-0">Tenggat paling dekat</h2>
            <a href="{{ route('events.index', ['sort' => 'paling_dekat']) }}" class="small">Lihat semua</a>
        </div>
        <div class="row g-3">
            @foreach ($terdekat as $event)
                <div class="col-sm-6 col-lg-4">
                    @include('partials.event-card', ['event' => $event, 'savedMap' => $savedMap])
                </div>
            @endforeach
        </div>
    </section>
@endif

@if ($terbaru->isNotEmpty())
    <section>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h5 mb-0">Baru diumumkan</h2>
            <a href="{{ route('events.index') }}" class="small">Lihat semua</a>
        </div>
        <div class="row g-3">
            @foreach ($terbaru as $event)
                <div class="col-sm-6 col-lg-4">
                    @include('partials.event-card', ['event' => $event, 'savedMap' => $savedMap])
                </div>
            @endforeach
        </div>
    </section>
@endif
@endsection
