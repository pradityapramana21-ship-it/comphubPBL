@extends('layouts.app')

@section('title', 'Hubungi Kami')

@section('content')
<div class="container" style="max-width: 640px;">
    <h1>Hubungi kami</h1>
    <p class="lead">
        Ada kegiatan yang informasinya salah? Pengajuan Anda ditolak tapi alasan
        kurang jelas? Sampaikan di sini.
    </p>

    <div class="card mb-4">
        <div class="card__body">
            <h2 class="h6 mb-2">Laporkan kegiatan yang bermasalah</h2>
            <p class="small text-muted-soft mb-0">
                Gunakan tombol "Ada yang salah? Laporkan" di halaman kegiatan
                yang bermasalah. Cara itu lebih cepat dan langsung masuk ke antrean
                administrator.
            </p>
        </div>
    </div>

    <div class="card">
        <div class="card__body">
            <h2 class="h6 mb-3">Kontak lain</h2>
            <p class="small mb-2">
                <strong>Soal pengajuan kegiatan:</strong><br>
                Gunakan panel penyedia Anda. Status pengajuan beserta alasan dari
                administrator tercatat di sana.
            </p>
            <p class="small mb-2">
                <strong>Soal akun:</strong><br>
                Masuk, lalu buka <a href="{{ route('user.profile.edit') }}">halaman profil</a>.
            </p>
            <p class="small mb-0">
                <strong>Bug atau keluhan teknis:</strong><br>
                Hubungi tim pengelola melalui saluran resmi kampus.
            </p>
        </div>
    </div>

    <div class="alert alert-warning mt-4" role="note">
        <p class="mb-0 small">
            Halaman ini tidak punya formulir kontak. Sengaja: kontak yang masuk lewat
            formulir web tidak punya jejak yang bisa diaudit, dan pada situs sebesar
            ini formulir cepat jadi tempat spam. Semua laporan yang bisa ditangani
            otomatis memang punya alurnya sendiri&mdash;dan semuanya tercatat.
        </p>
    </div>
</div>
@endsection
