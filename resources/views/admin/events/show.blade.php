@extends('layouts.admin')

@section('title', 'Tinjau: '.\Illuminate\Support\Str::limit($event->title, 40))
@section('heading', 'Tinjau Kegiatan')
@section('subheading', $event->providerProfile->organization_name)

@section('content')
<div class="row g-4">
    <div class="col-lg-7">
        {{-- Temuan otomatis ditampilkan DI ATAS keputusan. Admin yang tahu
             sebuah kegiatan kena aturan duplikasi punya peluang lebih besar
             menilai dengan benar. --}}
        @if ($autoFlags->isNotEmpty())
            <div class="card border-danger mb-4">
                <div class="card__body">
                    <h2 class="h6 mb-3">Penandaan otomatis</h2>
                    @php $rejectAt = config('moderation.thresholds.reject'); @endphp
                    @foreach ($autoFlags as $flag)
                        <div class="alert alert-{{ $flag->risk_score >= $rejectAt ? 'danger' : 'warning' }} py-2 px-3 mb-2">
                            <div class="d-flex justify-content-between gap-2 mb-1">
                                <code class="small">{{ $flag->rule_key ?? 'umum' }}</code>
                                <span class="small">{{ $flag->action->label() }} &middot; +{{ $flag->risk_score }}</span>
                            </div>
                            <p class="small mb-0">{{ $flag->reason }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="alert alert-success mb-4">
                <p class="mb-0 small">Tidak ada aturan otomatis yang menyala pada pengajuan ini.</p>
            </div>
        @endif

        <div class="card mb-4">
            <div class="card__body">
                <div class="d-flex gap-2 mb-3">
                    @include('partials.status-badge', ['event' => $event])
                    @if ($event->risk_score > 0)
                        <span class="badge-soft badge-soft--neutral">skor {{ $event->risk_score }}/100</span>
                    @endif
                </div>

                <h2 class="h5 mb-3">{{ $event->title }}</h2>

                <dl class="row mb-3">
                    <dt class="col-sm-4 text-muted-soft fw-normal">Waktu</dt>
                    <dd class="col-sm-8">{{ $dateHelper->longDate($event->event_start_at) }}</dd>

                    <dt class="col-sm-4 text-muted-soft fw-normal">Tenggat</dt>
                    <dd class="col-sm-8">{{ $dateHelper->dateTime($event->registration_deadline) }}</dd>

                    <dt class="col-sm-4 text-muted-soft fw-normal">Bentuk</dt>
                    <dd class="col-sm-8">{{ $event->mode->label() }}{{ $event->location_text ? ' — '.$event->location_text : '' }}</dd>

                    <dt class="col-sm-4 text-muted-soft fw-normal">Organisasi</dt>
                    <dd class="col-sm-8">
                        {{ $event->providerProfile->organization_name }}
                        @include('partials.status-badge', ['event' => (object) ['status' => $event->providerProfile->verification_status]])
                    </dd>
                </dl>

                <h3 class="h6 mb-2">Deskripsi</h3>
                <div style="white-space: pre-line;" class="mb-3">{{ $event->description }}</div>

                <h3 class="h6 mb-2">Tautan pendaftaran</h3>
                <p class="mb-0">
                    <a href="{{ $event->registration_url }}" target="_blank" rel="noopener noreferrer nofollow"
                       class="text-break">{{ $event->registration_url }}</a>
                </p>
            </div>
        </div>

        @if ($reports->isNotEmpty())
            <div class="card">
                <div class="card__body">
                    <h2 class="h6 mb-3">Laporan pengguna ({{ $reports->count() }})</h2>
                    @foreach ($reports as $report)
                        <div class="pb-2 mb-2 border-bottom">
                            <div class="d-flex justify-content-between gap-2">
                                <span class="badge-soft badge-soft--{{ $report->statusTone() }}">{{ $report->status->label() }}</span>
                                <span class="small text-muted-soft">{{ $dateHelper->date($report->created_at) }}</span>
                            </div>
                            <p class="small fw-semibold mb-1">{{ $report->reason_code->label() }}</p>
                            <p class="small mb-0">{{ $report->description }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <div class="col-lg-5">
        <div class="card mb-4" style="position: sticky; top: 1rem;">
            <div class="card__body">
                <h2 class="h6 mb-3">Keputusan</h2>

                @if ($allowedDecisions === [])
                    <div class="alert alert-info small mb-0">
                        Status saat ini ({{ $event->status->label() }}) tidak punya transisi
                        yang valid. Gunakan menu Arsipkan dari daftar kegiatan bila perlu.
                    </div>
                @else
                    <form method="POST" action="{{ route('admin.events.moderate', $event) }}"
                          data-confirm="Yakin mengambil keputusan ini? Tindakan dicatat permanen di jejak audit.">
                        @csrf

                        <div class="form-field">
                            <label for="decision" class="form-label">Keputusan</label>
                            <select id="decision" name="decision" class="form-select" required>
                                @foreach ($allowedDecisions as $action)
                                    <option value="{{ $action->value }}">{{ $action->label() }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-field">
                            <label for="reason" class="form-label">
                                Alasan
                                <span class="text-muted-soft">(wajib kecuali menyetujui)</span>
                            </label>
                            <textarea id="reason" name="reason" rows="4" class="form-control"
                                      placeholder="Tuliskan apa yang perlu diperbaiki oleh penyedia."></textarea>
                            <p class="form-hint">
                                Penyedia membaca alasan ini secara utuh. "Tidak memenuhi syarat"
                                tanpa penjelasan tidak akan pernah ditindaklanjuti.
                            </p>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Simpan keputusan</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card__body">
                <h2 class="h6 mb-3">Riwayat</h2>
                @if ($history->isEmpty())
                    <p class="text-muted-soft mb-0">Belum ada riwayat.</p>
                @else
                    <div class="stack-2">
                        @foreach ($history as $log)
                            <div class="pb-2 border-bottom">
                                <div class="d-flex justify-content-between gap-2">
                                    <span class="small fw-semibold">{{ $log->action->label() }}</span>
                                    <span class="small text-muted-soft">{{ $dateHelper->date($log->created_at) }}</span>
                                </div>
                                <span class="small text-muted-soft">{{ $log->actorLabel() }}</span>
                                @if ($log->reason)
                                    <p class="small mb-0 mt-1">{{ $log->reason }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
