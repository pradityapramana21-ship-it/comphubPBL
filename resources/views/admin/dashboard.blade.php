@extends('layouts.admin')

@section('title', 'Dasbor Admin')
@section('heading', 'Dasbor Administrator')
@section('subheading', 'Antrean yang menunggu keputusan ditampilkan lebih dulu, karena itulah pekerjaan hari ini.')

@section('content')
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat">
            <p class="stat__label">Pengguna</p>
            <p class="stat__value">{{ number_format($stats['totalUser'], 0, ',', '.') }}</p>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat stat--info">
            <p class="stat__label">Penyelenggara</p>
            <p class="stat__value">{{ number_format($stats['totalProvider'], 0, ',', '.') }}</p>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat">
            <p class="stat__label">Total kegiatan</p>
            <p class="stat__value">{{ number_format($stats['totalEvent'], 0, ',', '.') }}</p>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat {{ $stats['openReports'] > 0 ? 'stat--danger' : '' }}">
            <p class="stat__label">Laporan terbuka</p>
            <p class="stat__value">{{ number_format($stats['openReports'], 0, ',', '.') }}</p>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card__body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h6 mb-0">Antrean tinjauan kegiatan</h2>
                    <a href="{{ route('admin.events.index') }}" class="small">Buka antrean</a>
                </div>

                @if ($reviewQueue->isEmpty())
                    <p class="text-muted-soft mb-0">Tidak ada kegiatan yang menunggu ditinjau.</p>
                @else
                    <div class="stack-2">
                        @foreach ($reviewQueue as $event)
                            <div class="d-flex justify-content-between align-items-start gap-2 pb-2 border-bottom">
                                <div style="min-width: 0;">
                                    <a href="{{ route('admin.events.show', $event) }}" class="d-block text-truncate fw-semibold">
                                        {{ $event->title }}
                                    </a>
                                    <span class="small text-muted-soft">
                                        {{ $event->providerProfile->organization_name }}
                                    </span>
                                </div>
                                @if ($event->risk_score >= config('moderation.thresholds.reject'))
                                    <span class="badge-soft badge-soft--danger">skor {{ $event->risk_score }}</span>
                                @elseif ($event->risk_score > 0)
                                    <span class="badge-soft badge-soft--warning">skor {{ $event->risk_score }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card__body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h6 mb-0">Verifikasi organisasi</h2>
                    <a href="{{ route('admin.providers.index') }}" class="small">Buka antrean</a>
                </div>

                @if ($verificationQueue->isEmpty())
                    <p class="text-muted-soft mb-0">Tidak ada organisasi yang menunggu.</p>
                @else
                    <div class="stack-2">
                        @foreach ($verificationQueue as $provider)
                            <div class="d-flex justify-content-between align-items-center gap-2 pb-2 border-bottom">
                                <span class="text-truncate">{{ $provider->organization_name }}</span>
                                <span class="small text-muted-soft text-nowrap">
                                    {{ $dateHelper->date($provider->created_at) }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card__body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h6 mb-0">Laporan pengguna</h2>
                    <a href="{{ route('admin.reports.index') }}" class="small">Buka antrean</a>
                </div>

                @if ($reportQueue->isEmpty())
                    <p class="text-muted-soft mb-0">Tidak ada laporan terbuka.</p>
                @else
                    <div class="stack-2">
                        @foreach ($reportQueue as $report)
                            <div class="d-flex justify-content-between align-items-start gap-2 pb-2 border-bottom">
                                <span class="text-truncate">{{ $report->event->title }}</span>
                                <span class="badge-soft badge-soft--{{ $report->reason_code->isUrgent() ? 'danger' : 'neutral' }}">
                                    {{ $report->reason_code->label() }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card__body">
                <h2 class="h6 mb-3">Distribusi status kegiatan</h2>
                <div class="row g-2">
                    @foreach (\App\Enums\EventStatus::cases() as $status)
                        <div class="col-6 col-sm-4">
                            <div class="d-flex justify-content-between align-items-center gap-2">
                                <span class="badge-soft badge-soft--{{ $status->tone() }}">{{ $status->label() }}</span>
                                <span class="small">{{ $perStatus[$status->value] ?? 0 }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if ($ruleHits !== [])
                    <hr>
                    <h3 class="sidebar__label mt-3">Aturan yang paling sering menyala</h3>
                    <div class="stack-1">
                        @php $hits = collect($ruleHits)->sortDesc()->take(5); @endphp
                        @foreach ($hits as $rule => $total)
                            <div class="d-flex justify-content-between small">
                                <code>{{ $rule }}</code>
                                <span>{{ $total }}x</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
