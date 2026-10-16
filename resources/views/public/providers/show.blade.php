@extends('layouts.app')

@section('title', $provider->organization_name)
@section('description', $provider->campus_name)

@section('content')
<nav aria-label="Remah roti" class="small text-muted-soft mb-3">
    <a href="{{ route('providers.index') }}">Penyelenggara</a>
    <span aria-hidden="true">/</span>
    <span>{{ $provider->organization_name }}</span>
</nav>

<div class="card mb-4">
    <div class="card__body">
        <div class="d-flex flex-wrap justify-content-between gap-3">
            <div>
                <h1 class="h3 mb-1">{{ $provider->organization_name }}</h1>
                <p class="text-muted-soft mb-2">{{ $provider->campus_name }}</p>
                @if ($provider->websiteHost())
                    <p class="mono small mb-0">{{ $provider->websiteHost() }}</p>
                @endif
            </div>
            <div class="text-sm-end">
                <p class="badge-soft badge-soft--success">
                    <svg width="13" height="13" aria-hidden="true"><use href="{{ asset('images/icon-sprite.svg') }}#i-check"></use></svg>
                    Terverifikasi
                </p>
                @if ($provider->verified_at)
                    <p class="small text-muted-soft mb-0 mt-2">
                        Diperiksa {{ $dateHelper->date($provider->verified_at) }}
                    </p>
                @endif
            </div>
        </div>

        @if ($provider->description)
            <hr>
            <p class="mb-0">{{ $provider->description }}</p>
        @endif
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
        <div class="stat">
            <p class="stat__label">Kegiatan tayang</p>
            <p class="stat__value">{{ $events->total() }}</p>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="stat stat--info">
            <p class="stat__label">Total disimpan</p>
            <p class="stat__value">{{ number_format($totalTersimpan, 0, ',', '.') }}</p>
        </div>
    </div>
</div>

<h2 class="h5 mb-3">Kegiatan yang dipublikasikan</h2>

@if ($events->isEmpty())
    @include('partials.empty-state', [
        'title' => 'Belum ada kegiatan tayang',
        'text' => 'Organisasi ini sudah terverifikasi, tetapi belum punya kegiatan yang disetujui.',
    ])
@else
    <div class="row g-3">
        @foreach ($events as $event)
            <div class="col-sm-6 col-lg-4">
                @include('partials.event-card', ['event' => $event, 'savedMap' => []])
            </div>
        @endforeach
    </div>
@endif
@endsection
