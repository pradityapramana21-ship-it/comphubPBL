@extends('layouts.app')

@section('title', 'Tersimpan')

@section('content')
@php
    $savedMap = app(\App\Services\BookmarkService::class)->savedMap(auth()->user(), $bookmarks->pluck('id')->all());
@endphp

<div class="page-head">
    <h1 class="page-head__title">Tersimpan</h1>
    <p class="page-head__subtitle">
        {{ number_format($bookmarks->total(), 0, ',', '.') }} kegiatan tersimpan.
        Kegiatan yang sudah lewat tidak lagi dimunculkan.
    </p>
</div>

@if ($bookmarks->isEmpty())
    @include('partials.empty-state', [
        'title' => 'Belum ada kegiatan tersimpan',
        'text' => 'Tekan tombol Simpan pada kegiatan yang menarik agar tidak terlewat saat tenggatnya dekat.',
    ])
@else
    <div class="row g-3">
        @foreach ($bookmarks as $event)
            <div class="col-sm-6 col-lg-4">
                @include('partials.event-card', ['event' => $event, 'savedMap' => $savedMap])
            </div>
        @endforeach
    </div>

    @include('partials.pagination', ['paginator' => $bookmarks])
@endif
@endsection
