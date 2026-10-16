@extends('layouts.app')

@section('title', 'Kegiatan Saya')

@section('content')
<div class="page-head d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div>
        <h1 class="page-head__title">Kegiatan saya</h1>
        <p class="page-head__subtitle">
            {{ number_format($events->total(), 0, ',', '.') }} kegiatan.
            Draf dan kegiatan yang ditolak tidak terlihat publik.
        </p>
    </div>
    <a href="{{ route('provider.events.create') }}" class="btn btn-primary">Buat kegiatan</a>
</div>

<form id="filter-admin" method="GET" action="{{ route('provider.events.index') }}" class="mb-3">
    <div class="row g-2">
        <div class="col-sm-6 col-md-4">
            <label for="q" class="visually-hidden">Cari</label>
            <input type="search" id="q" name="q" class="form-control" value="{{ $filter->keyword }}"
                   placeholder="Cari judul kegiatan&hellip;" data-search-input>
        </div>
        <div class="col-sm-6 col-md-3">
            <label for="status" class="visually-hidden">Status</label>
            <select id="status" name="status" class="form-select">
                <option value="">Semua status</option>
                @foreach (\App\Enums\EventStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected($filter->status === $status)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-auto">
            <button type="submit" class="btn btn-outline-primary">Saring</button>
        </div>
    </div>
</form>

@if ($events->isEmpty())
    @include('partials.empty-state', [
        'title' => 'Belum ada kegiatan',
        'text' => 'Buat kegiatan sebagai draf dulu, lalu ajukan ketika sudah siap.',
    ])
@else
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <caption class="visually-hidden">Daftar kegiatan milik organisasi Anda</caption>
                <thead>
                    <tr>
                        <th scope="col">Kegiatan</th>
                        <th scope="col">Status</th>
                        <th scope="col">Tenggat</th>
                        <th scope="col" class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($events as $event)
                        <tr>
                            <td>
                                <a href="{{ route('provider.events.show', $event) }}" class="fw-semibold d-block">
                                    {{ $event->title }}
                                </a>
                                <span class="small text-muted-soft">
                                    {{ $event->category->name }} &middot; {{ $dateHelper->date($event->event_start_at) }}
                                </span>
                            </td>
                            <td>@include('partials.status-badge', ['event' => $event])</td>
                            <td class="small">@include('partials.deadline-timer', ['event' => $event])</td>
                            <td class="text-end text-nowrap">
                                @if ($event->isEditableByProvider())
                                    <a href="{{ route('provider.events.edit', $event) }}"
                                       class="btn btn-sm btn-outline-primary">Perbaiki</a>
                                @endif
                                <a href="{{ route('provider.events.show', $event) }}"
                                   class="btn btn-sm btn-outline-secondary">Rincian</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @include('partials.pagination', ['paginator' => $events])
@endif
@endsection
