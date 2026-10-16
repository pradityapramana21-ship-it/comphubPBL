@extends('layouts.app')

@section('title', $event->title)

@section('content')
<div class="container" style="max-width: 820px;">
    <nav aria-label="Remah roti" class="small text-muted-soft mb-3">
        <a href="{{ route('provider.events.index') }}">Kegiatan saya</a>
        <span aria-hidden="true">/</span>
        <span>Rincian</span>
    </nav>

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="h3 mb-2">{{ $event->title }}</h1>
            @include('partials.status-badge', ['event' => $event])
        </div>

        <div class="d-flex gap-2">
            @if ($event->isEditableByProvider())
                <a href="{{ route('provider.events.edit', $event) }}" class="btn btn-outline-primary">Sunting</a>
            @endif

            @if (($canSubmit ?? true) && $event->status->canTransitionTo(\App\Enums\EventStatus::Pending))
                <form method="POST" action="{{ route('provider.events.submit', $event) }}"
                      data-confirm="Ajukan kegiatan ini untuk ditinjau administrator?">
                    @csrf
                    <button type="submit" class="btn btn-primary">Ajukan untuk ditinjau</button>
                </form>
            @endif

            @if ($event->isPublished())
                <a href="{{ route('events.show', $event) }}" class="btn btn-outline-secondary">Lihat halaman publik</a>
            @endif
        </div>
    </div>

    @if ($event->status->value === 'changes_requested' || $event->status->value === 'rejected')
        <div class="alert alert-danger">
            <p class="fw-semibold mb-1">Catatan administrator:</p>
            <p class="mb-0">{{ $event->rejection_reason }}</p>
        </div>
    @endif

    @if ($event->risk_score > 0)
        <div class="alert alert-warning">
            <p class="fw-semibold mb-1">
                Hasil pemeriksaan otomatis: skor {{ $event->risk_score }} dari 100
            </p>
            <p class="small mb-0">
                {{ config('moderation.thresholds.reject') }} ke atas berarti otomatis ditolak;
                {{ config('moderation.thresholds.flag') }} ke atas masuk antrean prioritas.
                Semua temuan dicatat di riwayat di bawah.
            </p>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card__body">
            <dl class="row mb-0">
                <dt class="col-sm-4 text-muted-soft fw-normal">Waktu</dt>
                <dd class="col-sm-8">{{ $dateHelper->longDate($event->event_start_at) }}</dd>

                <dt class="col-sm-4 text-muted-soft fw-normal">Tenggat</dt>
                <dd class="col-sm-8">@include('partials.deadline-timer', ['event' => $event])</dd>

                <dt class="col-sm-4 text-muted-soft fw-normal">Bentuk</dt>
                <dd class="col-sm-8">{{ $event->mode->label() }}{{ $event->location_text ? ' — '.$event->location_text : '' }}</dd>

                <dt class="col-sm-4 text-muted-soft fw-normal">Kategori</dt>
                <dd class="col-sm-8">{{ $event->category->name }}</dd>
            </dl>
        </div>
    </div>

    <h2 class="h5 mb-3">Riwayat keputusan</h2>

    @if ($history->isEmpty())
        <p class="text-muted-soft">Belum ada riwayat.</p>
    @else
        <div class="card">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <caption class="visually-hidden">Riwayat keputusan moderasi kegiatan ini</caption>
                    <thead>
                        <tr>
                            <th scope="col">Waktu</th>
                            <th scope="col">Aksi</th>
                            <th scope="col">Pelaku</th>
                            <th scope="col">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($history as $log)
                            <tr>
                                <td class="small text-nowrap">{{ $dateHelper->dateTime($log->created_at) }}</td>
                                <td class="small">{{ $log->action->label() }}</td>
                                <td class="small">{{ $log->actorLabel() }}</td>
                                <td class="small text-muted-soft">{{ $log->reason }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
