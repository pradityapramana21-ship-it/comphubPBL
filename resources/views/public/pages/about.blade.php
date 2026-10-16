@extends('layouts.app')

@section('title', 'Tentang')

@section('content')
<div class="container" style="max-width: var(--container-narrow);">
    <h1>Tentang {{ config('app.name') }}</h1>

    <p class="lead">
        Katalog kegiatan mahasiswa: lomba, webinar, dan seminar yang disusun oleh
        Badan Eksekutif Mahasiswa, himpunan, unit kegiatan, serta fakultas
        dari dalam maupun luar kampus.
    </p>

    <h2 class="h5 mt-5 mb-2">Mengapa katalog ini perlu ada</h2>
    <p>
        Informasi kegiatan mahasiswa biasanya tersebar di media sosial, grup percakapan,
        dan poster. Sering hilang di antara satu organisasi dengan yang lain, dan
        yang tersisa sering kali sudah kedaluwarsa atau tautannya sudah rusak.
    </p>

    <h2 class="h5 mt-4 mb-2">Aturan main di sini</h2>
    <div class="card">
        <div class="card__body">
            <ul class="mb-0">
                <li class="mb-2">Penyedia <strong>tidak bisa</strong> menerbitkan karyanya sendiri.</li>
                <li class="mb-2">Setiap pengajuan melewati pemeriksaan otomatis, lalu ditinjau administrator.</li>
                <li class="mb-2">Penolakan selalu disertai alasan yang bisa ditindaklanjuti.</li>
                <li class="mb-2">Tenggat yang lewat otomatis hilang dari daftar pendaftaran aktif.</li>
                <li>Identitas orang yang melapor kegiatan tidak pernah dibagikan ke penyedia.</li>
            </ul>
        </div>
    </div>

    <h2 class="h5 mt-5 mb-2">Dari mana data ini berasal</h2>
    <p>
        Setiap organisasi yang tampil di sini sudah diverifikasi oleh administrator.
        Verifikasi berarti data organisasi diperiksa sebelum kegiatan pertama mereka
        ditayangkan&mdash;dan bisa ditinjau ulang kapan saja.
    </p>

    <h2 class="h5 mt-4 mb-2">Proyek ini</h2>
    <p>
        {{ config('app.name') }} adalah proyek <em>Project Based Learning</em> oleh tim
        lima mahasiswa: IT Manager, Backend Developer, Frontend Developer, UI/UX Designer,
        dan Dokumentasi &amp; QA.
    </p>

    <div class="mt-4">
        <a href="{{ route('pages.moderation') }}" class="btn btn-outline-primary">Baca kebijakan moderasi</a>
        <a href="{{ route('events.index') }}" class="btn btn-primary">Jelajahi kegiatan</a>
    </div>
</div>
@endsection
