@extends('layouts.app')

@section('title', 'Penyelenggara')

@section('content')
<div class="page-head">
    <h1 class="page-head__title">Penyelenggara</h1>
    <p class="page-head__subtitle">
        Organisasi yang datanya sudah diperiksa administrator. Hanya profil terverifikasi yang dimunculkan.
    </p>
</div>

<form method="GET" action="{{ route('providers.index') }}" class="mb-4" role="search">
    <div class="row g-2">
        <div class="col-sm-8 col-md-6">
            <label for="q" class="visually-hidden">Cari penyelenggara</label>
            <input type="search" id="q" name="q" class="form-control" value="{{ $keyword }}"
                   placeholder="Cari nama organisasi atau kampus&hellip;" autocomplete="off">
        </div>
        <div class="col-sm-4 col-md-auto">
            <button type="submit" class="btn btn-primary w-100">Cari</button>
        </div>
    </div>
</form>

@if ($providers->isEmpty())
    @include('partials.empty-state', [
        'title' => 'Penyelenggara tidak ditemukan',
        'text' => 'Belum ada organisasi yang cocok dengan pencarian itu. Penyelenggara yang ingin tampil di sini bisa mendaftar lebih dulu.',
    ])
@else
    <div class="row g-3">
        @foreach ($providers as $provider)
            <div class="col-sm-6 col-lg-4">
                <div class="card card--hoverable h-100">
                    <div class="card__body d-flex flex-column">
                        <h2 class="h6 mb-1">
                            <a href="{{ route('providers.show', $provider->routeSlug()) }}" class="text-decoration-none">
                                {{ $provider->organization_name }}
                            </a>
                        </h2>
                        <p class="small text-muted-soft mb-2">{{ $provider->campus_name }}</p>

                        @if ($provider->websiteHost())
                            <p class="small mono text-muted-soft mb-3">{{ $provider->websiteHost() }}</p>
                        @endif

                        <div class="mt-auto d-flex justify-content-between align-items-center">
                            <span class="badge-soft badge-soft--success">Terverifikasi</span>
                            <span class="small text-muted-soft">
                                {{ $provider->events_count }} kegiatan
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @include('partials.pagination', ['paginator' => $providers])
@endif
@endsection
