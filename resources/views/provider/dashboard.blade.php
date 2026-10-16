@extends('layouts.app')

@section('title', 'Dasbor Penyelenggara')

@section('content')
<div class="page-head d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div>
        <h1 class="page-head__title">{{ $profile->organization_name }}</h1>
        <p class="page-head__subtitle">Kelola kegiatan dan pantau posisinya di antrean tinjauan.</p>
    </div>
    <a href="{{ route('provider.events.create') }}" class="btn btn-primary">Buat kegiatan</a>
</div>

<div class="mb-4">
    @include('partials.status-badge', ['event' => (object) ['status' => $profile->verification_status]])
    <span class="text-muted-soft small ms-2">{{ $profile->campus_name }}</span>
    <a href="{{ route('provider.profile.edit') }}" class="small ms-2">Ubah data organisasi</a>
</div>

@if ($needsAction->isNotEmpty())
    <div class="alert alert-warning" role="alert">
        <p class="fw-semibold mb-1">{{ $needsAction->count() }} kegiatan perlu tindakan Anda.</p>
        <p class="small mb-0">
            Satu kegiatan ditolak dan {{ max(0, $needsAction->count() - 1) }} diminta diperbaiki.
            Buka kartu-kartu di bawah untuk melihat alasannya.
        </p>
    </div>
@endif

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-md-3">
        <div class="stat">
            <p class="stat__label">Total kegiatan</p>
            <p class="stat__value">{{ $recent->total() }}</p>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="stat stat--info">
            <p class="stat__label">Menunggu tinjauan</p>
            <p class="stat__value">{{ $perStatus['pending'] ?? 0 }}</p>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="stat stat--warning">
            <p class="stat__label">Perlu diperbaiki</p>
            <p class="stat__value">{{ $perStatus['changes_requested'] ?? 0 }}</p>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="stat">
            <p class="stat__label">Total disimpan</p>
            <p class="stat__value">{{ number_format($totalTersimpan, 0, ',', '.') }}</p>
        </div>
    </div>
</div>

<h2 class="h5 mb-3">Kegiatan Anda</h2>

@if ($recent->isEmpty())
    @include('partials.empty-state', [
        'title' => 'Belum ada kegiatan',
        'text' => 'Buat kegiatan sebagai draf dulu. Draf tidak terlihat publik, jadi aman ditulis bertahap.',
    ])
@else
    <div class="row g-3">
        @foreach ($recent as $event)
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card__body">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <span class="badge-soft badge-soft--neutral">{{ $event->category->name }}</span>
                            @include('partials.status-badge', ['event' => $event])
                        </div>

                        <h3 class="h6 mb-2">
                            <a href="{{ route('provider.events.show', $event) }}">{{ $event->title }}</a>
                        </h3>

                        @if ($event->risk_score > 0)
                            <p class="small text-muted-soft mb-2">
                                Skor pemeriksaan otomatis:
                                <span class="mono">{{ $event->risk_score }}</span>/100
                            </p>
                        @endif

                        @if ($event->status->value === 'changes_requested' || $event->status->value === 'rejected')
                            <div class="alert alert-danger small py-2 mb-2">
                                <strong>Catatan administrator:</strong><br>
                                {{ $event->rejection_reason }}
                            </div>
                        @endif

                        <div class="d-flex gap-2 flex-wrap">
                            @if ($event->isEditableByProvider())
                                <a href="{{ route('provider.events.edit', $event) }}" class="btn btn-sm btn-outline-primary">Perbaiki</a>
                            @endif
                            <a href="{{ route('provider.events.show', $event) }}" class="btn btn-sm btn-outline-secondary">Rincian</a>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @include('partials.pagination', ['paginator' => $recent])
@endif
@endsection
