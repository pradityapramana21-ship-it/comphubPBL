@extends('layouts.app')

@section('title', 'Panduan Penyelenggara')

@section('content')
<div class="container" style="max-width: var(--container-narrow);">
    <h1>Panduan penyelenggara</h1>

    <p class="lead">
        Cara memposting kegiatan supaya cepat ditinjau dan tidak ditolak.
    </p>

    @guest
        <div class="alert alert-primary d-flex flex-wrap gap-3 align-items-center justify-content-between">
            <span>Mau memasang kegiatan Anda di sini?</span>
            <a href="{{ route('auth.registerProvider') }}" class="btn btn-primary">Daftar sebagai penyelenggara</a>
        </div>
    @endguest

    <h2 class="h5 mt-5 mb-3">Langkah dari daftar sampai tayang</h2>
    <ol class="ps-3">
        <li class="mb-2"><strong>Daftar sebagai penyelenggara.</strong> Isi data organisasi Anda.</li>
        <li class="mb-2"><strong>Tunggu verifikasi.</strong> Admin memeriksa nama dan kontak resmi. Unggah dokumen bila diminta.</li>
        <li class="mb-2"><strong>Buat kegiatan sebagai draf.</strong> Draf tidak terlihat publik, jadi aman ditulis bertahap.</li>
        <li class="mb-2"><strong>Ajukan.</strong> Pemeriksaan otomatis berjalan, lalu masuk antrean admin.</li>
        <li><strong>Tayang.</strong> Setelah disetujui, kegiatan tampil di katalog sampai tenggat lewat.</li>
    </ol>

    <h2 class="h5 mt-5 mb-3">Agar tidak ditolak</h2>

    <div class="card mb-3">
        <div class="card__body">
            <h3 class="h6 mb-2">Judul harus spesifik</h3>
            <p class="small mb-0 text-muted-soft">
                "Lomba" saja tidak akan lolos. "Lomba Pemrograman Kompetitif 2026 untuk
                Mahasiswa se-Indonesia" jauh lebih mudah dikenali dan tidak
                tertukar dengan lomba lain.
            </p>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card__body">
            <h3 class="h6 mb-2">Deskripsi harus menjelaskan, bukan menjual</h3>
            <p class="small mb-0 text-muted-soft">
                Sebutkan apa kegiatannya, siapa sasarannya, dan apa yang didapat peserta.
                Kalimat promosi kosong hanya menambah pekerjaan bagi admin tanpa
                membantu siapa pun.
            </p>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card__body">
            <h3 class="h6 mb-2">Jangan tulis kontak langsung di deskripsi</h3>
            <p class="small mb-0 text-muted-soft">
                Nomor WhatsApp, Telegram, atau telepon di deskripsi akan ditandai.
                Gunakan kolom tautan pendaftaran yang sudah tersedia.
            </p>
        </div>
    </div>

    <div class="card">
        <div class="card__body">
            <h3 class="h6 mb-2">Tautan pendaftaran harus resmi</h3>
            <p class="small mb-0 text-muted-soft">
                Pakai tautan panjang dari situs resmi, bukan tautan pemendek seperti
                bit.ly. Tautan yang tidak bisa diverifikasi membuat admin curiga.
            </p>
        </div>
    </div>

    <h2 class="h5 mt-5 mb-3">Kalau kegiatan Anda ditolak</h2>
    <p>
        Alasannya selalu tersimpan di dashboard Anda. Perbaiki isinya, lalu ajukan
        lagi&mdash;keduanya memakai pemeriksaan yang sama, dan kegiatan yang sudah
        tayang tidak bisa disunting langsung. Tarik dulu, perbaiki, lalu ajukan ulang.
    </p>

    <div class="alert alert-info mt-4" role="note">
        <p class="mb-0 small">
            Baca juga <a href="{{ route('pages.moderation') }}">kebijakan moderasi</a>
            untuk melihat aturan otomatis yang dijalankan sistem beserta angkanya.
        </p>
    </div>
</div>
@endsection
