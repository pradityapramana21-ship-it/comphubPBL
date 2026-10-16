@extends('layouts.app')

@section('title', 'Kebijakan Moderasi')

@section('content')
<div class="container" style="max-width: var(--container-narrow);">
    <h1>Kebijakan moderasi</h1>
    <p class="lead">
        Halaman ini menjelaskan aturan yang benar-benar dipakai sistem, lengkap dengan
        angkanya. Kalau angkanya berbeda dengan yang tertulis di sini, yang tertulis
        di sini yang lebih benar &mdash; dan halaman ini yang perlu diperbarui.
    </p>

    <div class="alert alert-info d-flex gap-2" role="note">
        <svg width="20" height="20" aria-hidden="true" class="flex-shrink-0"><use href="{{ asset('images/icon-sprite.svg') }}#i-shield"></use></svg>
        <div>
            <strong>Tidak ada kegiatan yang terbit secara otomatis.</strong>
            Skor moderasi hanya menentukan seberapa mendesak sebuah pengajuan
            perlu dilihat manusia. Menyetujui kegiatan tetap keputusan admin.
        </div>
    </div>

    <h2 class="h5 mt-5 mb-3">Aturan yang dijalankan</h2>

    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <caption class="visually-hidden">Daftar aturan moderasi otomatis beserta bobotnya</caption>
            <thead>
                <tr>
                    <th scope="col">Aturan</th>
                    <th scope="col" class="text-end">Poin</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $labels = [
                        'keyword_blacklist' => 'Kata terlarang (judol, attrition, dan sejenisnya)',
                        'rate_limit' => 'Terlalu sering mengajukan kegiatan',
                        'duplicate' => 'Mirip dengan kegiatan milik organisasi yang sama',
                        'date_sanity' => 'Tanggal tidak masuk akal',
                        'suspicious_url' => 'Tautan mencurigakan (pemendek, tanpa HTTPS)',
                        'contact_leak' => 'Kontak langsung ditulis di deskripsi',
                        'content_quality' => 'Informasi belum cukup lengkap',
                    ];
                @endphp
                @foreach ($weights as $key => $weight)
                    <tr>
                        <td>{{ $labels[$key] ?? $key }}</td>
                        <td class="text-end"><span class="badge-soft badge-soft--{{ $weight >= 60 ? 'danger' : ($weight >= 25 ? 'warning' : 'neutral') }}">{{ $weight }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <h2 class="h5 mt-5 mb-3">Ambang keputusan</h2>
    <div class="row g-3">
        <div class="col-md-4">
            <div class="stat">
                <p class="stat__label">Skor 0 sampai {{ $thresholds['flag'] - 1 }}</p>
                <p class="stat__value">{{ $thresholds['flag'] }}</p>
                <p class="stat__hint">Masuk antrean tinjauan normal.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat stat--warning">
                <p class="stat__label">Skor {{ $thresholds['flag'] }} sampai {{ $thresholds['reject'] - 1 }}</p>
                <p class="stat__value">{{ $thresholds['flag'] }}</p>
                <p class="stat__hint">Tampil di urutan teratas antrean, dengan alasan yang wajib dibaca.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat stat--danger">
                <p class="stat__label">Skor {{ $thresholds['reject'] }} ke atas</p>
                <p class="stat__value">{{ $thresholds['reject'] }}</p>
                <p class="stat__hint">Ditolak otomatis. Penyedia bisa memperbaiki lalu mengajukan ulang.</p>
            </div>
        </div>
    </div>

    <h2 class="h5 mt-5 mb-3">Batas pengajuan</h2>
    <ul>
        <li>Maksimal <strong>{{ $rateLimit['per_hour'] }}</strong> pengajuan per jam per organisasi.</li>
        <li>Maksimal <strong>{{ $rateLimit['per_day'] }}</strong> pengajuan per 24 jam.</li>
        <li>Setelah terlampaui, tunggu <strong>{{ $rateLimit['cooldown_minutes'] }} menit</strong> sebelum mencoba lagi.</li>
    </ul>

    <h2 class="h5 mt-5 mb-3">Apa yang TIDAK diblokir</h2>
    <div class="card">
        <div class="card__body">
            <ul class="mb-0">
                <li class="mb-2">
                    <strong>Domain baru.</strong> Situs baru yang belum pernah muncul
                    sebelumnya tidak diblokir hanya karena belum dikenal.
                </li>
                <li class="mb-2">
                    <strong>Judul mirip.</strong> Dua wave dari lomba yang sama bukan otomatis
                    duplikat&mdash;keduanya bisa sah.
                </li>
                <li>
                    <strong>Organisasi baru.</strong> Yang belum punya rekam jejak tetap
                    bisa mengajukan; hanya pemeriksaan kualitas isi yang dilonggarkan
                    setelah rekam jejaknya bersih.
                </li>
            </ul>
        </div>
    </div>

    <h2 class="h5 mt-5 mb-3">Kalau pengajuan Anda ditolak</h2>
    <p>
        Alasan penolakan selalu disimpan di dashboard Anda dan bisa dibaca ulang.
        Perbaiki isinya, lalu mengajukan kembali. Pengajuan yang diperbaiki
        tetap melalui pemeriksaan yang sama.
    </p>
</div>
@endsection
