@extends('layouts.admin')

@section('title', 'Tinjau Kegiatan')
@section('heading', 'Tinjau Kegiatan')
@section('subheading', 'Keputusan yang Anda ambil di sini menentukan apa yang dilihat seluruh mahasiswa.')

@section('content')
<div class="row g-4">
    <div class="col-xl-5">
        <div class="card">
            <div class="card__body">
                <h2 class="h6 mb-3">Antrean tinjauan ({{ $queue->total() }})</h2>
                <p class="small text-muted-soft mb-3">Diurutkan dari skor risiko tertinggi.</p>

                @if ($queue->isEmpty())
                    <p class="text-muted-soft mb-0">Antrean kosong.</p>
                @else
                    <div class="stack-2">
                        @foreach ($queue as $event)
                            <a href="{{ route('admin.events.show', $event) }}"
                               class="d-block p-2 border rounded text-decoration-none text-body">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <span class="fw-semibold small">{{ $event->title }}</span>
                                    @if ($event->risk_score > 0)
                                        <span class="badge-soft badge-soft--{{ $event->risk_score >= config('moderation.thresholds.reject') ? 'danger' : 'warning' }}">
                                            {{ $event->risk_score }}
                                        </span>
                                    @endif
                                </div>
                                <span class="small text-muted-soft">
                                    {{ $event->providerProfile->organization_name }} &middot; {{ $event->status->label() }}
                                </span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-xl-7">
        <form id="filter-admin" method="GET" action="{{ route('admin.events.index') }}" class="mb-3">
            <div class="row g-2">
                <div class="col-sm-5">
                    <label for="q" class="visually-hidden">Cari</label>
                    <input type="search" id="q" name="q" class="form-control"
                           value="{{ $filter->keyword }}" placeholder="Cari judul&hellip;" data-search-input>
                </div>
                <div class="col-sm-4">
                    <label for="status" class="visually-hidden">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">Semua status</option>
                        @foreach (\App\Enums\EventStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected($filter->status === $status)>
                                {{ $status->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-3">
                    <button type="submit" class="btn btn-outline-primary w-100">Saring</button>
                </div>
            </div>
        </form>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0">
                    <caption class="visually-hidden">Semua kegiatan beserta statusnya</caption>
                    <thead>
                        <tr>
                            <th scope="col">Kegiatan</th>
                            <th scope="col">Status</th>
                            <th scope="col">Skor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($all as $event)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.events.show', $event) }}" class="d-block small fw-semibold">
                                        {{ $event->title }}
                                    </a>
                                    <span class="small text-muted-soft">
                                        {{ $event->providerProfile?->organization_name ?? '—' }}
                                    </span>
                                </td>
                                <td>@include('partials.status-badge', ['event' => $event])</td>
                                <td>
                                    @if ($event->risk_score > 0)
                                        <span class="mono small">{{ $event->risk_score }}</span>
                                    @else
                                        <span class="small text-muted-soft">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted-soft py-4">Tidak ada kegiatan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @include('partials.pagination', ['paginator' => $all])
    </div>
</div>
@endsection
