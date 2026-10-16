@extends('layouts.admin')

@section('title', 'Laporan Pengguna')
@section('heading', 'Laporan Pengguna')
@section('subheading', 'Identitas pelapor tidak pernah dikirim ke penyedia kegiatan.')

@section('content')
<div class="row g-4">
    <div class="col-lg-7">
        <h2 class="h6 mb-3">Menunggu ditinjau ({{ $openCount }})</h2>

        @if ($pending->isEmpty())
            @include('partials.empty-state', [
                'title' => 'Tidak ada laporan terbuka',
                'text' => 'Semua laporan sudah ditangani atau tidak diteruskan.',
            ])
        @else
            <div class="stack-3">
                @foreach ($pending as $report)
                    <div class="card">
                        <div class="card__body">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                <div style="min-width: 0;">
                                    <a href="{{ route('events.show', $report->event) }}" class="small fw-semibold d-block text-truncate">
                                        {{ $report->event->title }}
                                    </a>
                                    <span class="small text-muted-soft">
                                        {{ $report->event->providerProfile->organization_name }}
                                    </span>
                                </div>
                                <span class="badge-soft badge-soft--{{ $report->reason_code->isUrgent() ? 'danger' : 'neutral' }} flex-shrink-0">
                                    {{ $report->reason_code->label() }}
                                </span>
                            </div>

                            @if ($report->description)
                                <p class="small mb-2">{{ $report->description }}</p>
                            @endif

                            <p class="small text-muted-soft mb-2">
                                {{ $dateHelper->dateTime($report->created_at) }}
                                &middot; status {{ $report->status->label() }}
                            </p>

                            <form method="POST" action="{{ route('admin.reports.resolve', $report) }}" class="mb-2">
                                @csrf
                                <div class="input-group input-group-sm">
                                    <label class="visually-hidden" for="catatan-{{ $report->id }}">Catatan penanganan</label>
                                    <input type="text" id="catatan-{{ $report->id }}" name="note"
                                           class="form-control" placeholder="Catatan penanganan (wajib)" minlength="5" required>
                                    <button type="submit" class="btn btn-success">Selesaikan</button>
                                </div>
                            </form>

                            <div class="d-flex gap-2">
                                @if ($report->status->value === 'open')
                                    <form method="POST" action="{{ route('admin.reports.open', $report) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-primary">Tandai ditinjau</button>
                                    </form>
                                @endif

                                <form method="POST" action="{{ route('admin.reports.dismiss', $report) }}">
                                    @csrf
                                    <input type="hidden" name="note" value="Tidak dilanjutkan setelah ditinjau.">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">Tidak diteruskan</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @include('partials.pagination', ['paginator' => $pending])
    </div>

    <div class="col-lg-5">
        <h2 class="h6 mb-3">Riwayat penanganan</h2>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <caption class="visually-hidden">Laporan yang sudah ditangani</caption>
                    <thead>
                        <tr>
                            <th scope="col">Kegiatan</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($history as $report)
                            <tr>
                                <td class="small">{{ \Illuminate\Support\Str::limit($report->event->title, 32) }}</td>
                                <td>
                                    <span class="badge-soft badge-soft--{{ $report->statusTone() }}">
                                        {{ $report->status->label() }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-center text-muted-soft py-4">Belum ada riwayat.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
