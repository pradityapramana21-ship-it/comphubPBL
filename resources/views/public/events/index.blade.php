@extends('layouts.app')

@section('title', 'Semua Kegiatan')

@section('content')
@php
    $savedMap = auth()->check()
        ? app(\App\Services\BookmarkService::class)->savedMap(auth()->user(), $events->pluck('id')->all())
        : [];

    $sortLabels = [
        'terbaru' => 'Paling baru',
        'paling_dekat' => 'Tenggat paling dekat',
        'acara_dulu' => 'Acara terdekat',
        'deadline_dulu' => 'Tenggat terlama',
        'jam_paling_banyak_disimpan' => 'Paling banyak disimpan',
    ];

    $activeChips = [];
    if ($filter->keyword) {
        $activeChips[] = ['key' => 'q', 'label' => 'Kata kunci: '.$filter->keyword];
    }
    if ($filter->categoryId) {
        $category = $categories->firstWhere('id', $filter->categoryId);
        $activeChips[] = ['key' => 'category', 'label' => 'Kategori: '.($category?->name ?? $filter->categoryId)];
    }
    if ($filter->mode) {
        $activeChips[] = ['key' => 'mode', 'label' => 'Bentuk: '.$filter->mode->label()];
    }
    if ($filter->startsFrom) {
        $activeChips[] = ['key' => 'from', 'label' => 'Mulai: '.$dateHelper->date($filter->startsFrom)];
    }
    if ($filter->startsUntil) {
        $activeChips[] = ['key' => 'until', 'label' => 'Sampai: '.$dateHelper->date($filter->startsUntil)];
    }
@endphp

<div class="page-head">
    <h1 class="page-head__title">Semua kegiatan</h1>
    <p class="page-head__subtitle">
        Menampilkan {{ number_format($events->total(), 0, ',', '.') }} kegiatan yang pendaftarannya masih dibuka.
    </p>
</div>

<div class="row g-4">
    {{-- Filter --}}
    <div class="col-lg-3">
        <form id="filter-katalog" class="card" method="GET" action="{{ route('events.index') }}" role="search">
            <div class="card__body">
                <h2 class="sidebar__label mb-3">Saring</h2>

                <div class="form-field">
                    <label for="q" class="form-label">Kata kunci</label>
                    <input type="search" id="q" name="q" class="form-control"
                           value="{{ $filter->keyword }}" data-search-input
                           placeholder="mis. pemrograman" autocomplete="off">
                </div>

                <div class="form-field">
                    <label for="category" class="form-label">Kategori</label>
                    <select id="category" name="category" class="form-select">
                        <option value="">Semua kategori</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected($category->id === $filter->categoryId)>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-field">
                    <label for="mode" class="form-label">Bentuk</label>
                    <select id="mode" name="mode" class="form-select">
                        <option value="">Semua bentuk</option>
                        @foreach (\App\Enums\EventMode::cases() as $mode)
                            <option value="{{ $mode->value }}" @selected($mode === $filter->mode)>
                                {{ $mode->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="row g-2">
                    <div class="col">
                        <label for="from" class="form-label">Mulai</label>
                        <input type="date" id="from" name="from" class="form-control"
                               value="{{ $filter->startsFrom ? $dateHelper->machineValue($filter->startsFrom) : '' }}">
                    </div>
                    <div class="col">
                        <label for="until" class="form-label">Sampai</label>
                        <input type="date" id="until" name="until" class="form-control"
                               value="{{ $filter->startsUntil ? $dateHelper->machineValue($filter->startsUntil) : '' }}">
                    </div>
                </div>

                <div class="form-field mt-3">
                    <label for="sort" class="form-label">Urutkan</label>
                    <select id="sort" name="sort" class="form-select">
                        @foreach ($sortLabels as $value => $label)
                            <option value="{{ $value }}" @selected($filter->sort === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <button type="submit" class="btn btn-primary w-100">Terapkan saringan</button>
                <button type="button" class="btn btn-link w-100 mt-1" data-filter-reset>Atur ulang</button>
            </div>
        </form>
    </div>

    {{-- Hasil --}}
    <div class="col-lg-9">
        @if ($activeChips !== [])
            <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
                <span class="small text-muted-soft">Saringan aktif:</span>
                @foreach ($activeChips as $chip)
                    <a href="#" class="badge-soft badge-soft--primary text-decoration-none"
                       data-filter-remove="{{ $chip['key'] }}"
                       title="Klik untuk menghapus saringan ini">
                        {{ $chip['label'] }} &times;
                    </a>
                @endforeach
            </div>
        @endif

        @if ($events->isEmpty())
            @include('partials.empty-state', [
                'icon' => 'search-empty',
                'title' => 'Tidak ada kegiatan yang cocok',
                'text' => 'Coba longgarkan saringan, atau kata kunci yang berbeda. Kegiatan yang pendaftarannya sudah ditutup juga tidak dimunculkan di sini.',
            ])
        @else
            <div class="row g-3">
                @foreach ($events as $event)
                    <div class="col-sm-6 col-xl-4">
                        @include('partials.event-card', ['event' => $event, 'savedMap' => $savedMap])
                    </div>
                @endforeach
            </div>

            @include('partials.pagination', ['paginator' => $events])
        @endif
    </div>
</div>
@endsection
