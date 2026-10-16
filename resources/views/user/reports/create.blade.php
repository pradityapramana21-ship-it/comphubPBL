@extends('layouts.app')

@section('title', 'Laporkan Kegiatan')

@section('content')
<div class="container" style="max-width: 640px;">
    <nav aria-label="Remah roti" class="small text-muted-soft mb-3">
        <a href="{{ route('events.index') }}">Kegiatan</a>
        <span aria-hidden="true">/</span>
        <a href="{{ route('events.show', $event) }}">{{ \Illuminate\Support\Str::limit($event->title, 40) }}</a>
    </nav>

    <h1 class="mb-1">Laporkan kegiatan ini</h1>
    <p class="text-muted-soft mb-4">
        Laporan Anda dibaca administrator. Identitas Anda <strong>tidak pernah</strong>
        dikirim kepada penyelenggara.
    </p>

    @if ($existing)
        <div class="alert alert-info" role="status">
            <p class="mb-1 fw-semibold">Anda sudah melapor kegiatan ini.</p>
            <p class="mb-0 small">
                Status saat ini: <strong>{{ $existing->status->label() }}</strong>.
                Anda bisa menarik laporan itu dari daftar Tersimpan.
            </p>
        </div>
    @else
        <form method="POST" action="{{ route('user.reports.store', $event) }}" data-validate>
            @csrf

            <div class="card mb-3">
                <div class="card__body">
                    @php
                        $reasons = collect(\App\Enums\ReportReason::cases())
                            ->mapWithKeys(fn($r) => [$r->value => $r->label()]);
                    @endphp

                    @include('partials.form-field', [
                        'name' => 'reason_code',
                        'label' => 'Apa yang salah?',
                        'type' => 'select',
                        'required' => true,
                        'options' => $reasons->all(),
                        'placeholder' => 'Pilih alasan',
                    ])

                    @include('partials.form-field', [
                        'name' => 'description',
                        'label' => 'Keterangan',
                        'type' => 'textarea',
                        'rows' => 4,
                        'maxlength' => 1000,
                        'data-counter' => 'hitung-keterangan',
                        'hint' => 'Semakin jelas, semakin cepat ditindaklanjuti.',
                    ])
                    <p class="form-hint text-end mb-0" id="hitung-keterangan">0 / 1000</p>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Kirim laporan</button>
            <a href="{{ route('events.show', $event) }}" class="btn btn-outline-secondary">Batal</a>
        </form>
    @endif
</div>
@endsection
