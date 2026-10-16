@extends('layouts.app')

@section('title', 'Dasbor')

@section('content')
@php
    $savedMap = app(\App\Services\BookmarkService::class)->savedMap($user, $tersimpan->pluck('id')->all());
@endphp

<div class="page-head d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div>
        <h1 class="page-head__title">Halo, {{ $user->name }}</h1>
        <p class="page-head__subtitle">Ringkasan kegiatan yang Anda simpan dan mendekati tenggat.</p>
    </div>
    <a href="{{ route('events.index') }}" class="btn btn-primary">Cari kegiatan</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-md-3">
        <div class="stat">
            <p class="stat__label">Kegiatan tersimpan</p>
            <p class="stat__value">{{ number_format($totalTersimpan, 0, ',', '.') }}</p>
            <p class="stat__hint"><a href="{{ route('user.bookmarks.index') }}">Lihat semua</a></p>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="stat stat--warning">
            <p class="stat__label">Mendekati tenggat</p>
            <p class="stat__value">{{ $disarankan->where(fn($e) => $e->deadlineTone() !== 'neutral')->count() }}</p>
            <p class="stat__hint">Tenggat dalam 7 hari</p>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="stat">
            <p class="stat__label">Laporan saya</p>
            <p class="stat__value">{{ $laporanSaya->count() }}</p>
        </div>
    </div>
</div>

@if ($tersimpan->isNotEmpty())
    <section class="mb-5">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h5 mb-0">Yang Anda simpan</h2>
            <a href="{{ route('user.bookmarks.index') }}" class="small">Lihat semua</a>
        </div>
        <div class="row g-3">
            @foreach ($tersimpan as $event)
                <div class="col-sm-6 col-lg-4">
                    @include('partials.event-card', ['event' => $event, 'savedMap' => $savedMap])
                </div>
            @endforeach
        </div>
    </section>
@endif

@if ($disarankan->isNotEmpty())
    <section>
        <h2 class="h5 mb-3">Tenggat paling dekat</h2>
        <div class="row g-3">
            @foreach ($disarankan as $event)
                <div class="col-sm-6 col-lg-4">
                    @include('partials.event-card', ['event' => $event, 'savedMap' => $savedMap])
                </div>
            @endforeach
        </div>
    </section>
@endif
@endsection
