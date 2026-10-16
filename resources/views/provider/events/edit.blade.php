@extends('layouts.app')

@section('title', 'Sunting Kegiatan')

@section('content')
<div class="container" style="max-width: 820px;">
    <nav aria-label="Remah roti" class="small text-muted-soft mb-3">
        <a href="{{ route('provider.events.index') }}">Kegiatan saya</a>
        <span aria-hidden="true">/</span>
        <span>Sunting</span>
    </nav>

    <h1 class="mb-1">Sunting kegiatan</h1>

    @if ($event->status->value === 'changes_requested' || $event->status->value === 'rejected')
        <div class="alert alert-danger">
            <p class="fw-semibold mb-1">Catatan administrator:</p>
            <p class="mb-0 small">{{ $event->rejection_reason }}</p>
        </div>
    @endif

    <div class="alert alert-info">
        <p class="mb-0 small">
            Setelah diperbaiki, tekan <strong>Ajukan ulang</strong> untuk masuk ke antrean
            tinjauan. Pemeriksaan otomatis akan berjalan lagi dari awal.
        </p>
    </div>

    <form method="POST" action="{{ route('provider.events.update', $event) }}" data-validate>
        @csrf
        @method('PUT')
        @include('provider.events._form', ['event' => $event, 'categories' => $categories])
        <button type="submit" class="btn btn-primary">Simpan perubahan</button>
        <a href="{{ route('provider.events.show', $event) }}" class="btn btn-outline-secondary">Batal</a>
    </form>
</div>
@endsection
