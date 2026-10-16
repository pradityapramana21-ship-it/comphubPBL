@extends('layouts.app')

@section('title', 'Halaman tidak ditemukan')

@section('content')
<div class="empty-state">
    <p class="empty-state__icon" aria-hidden="true">404</p>
    <h1 class="h4 mb-2">Halaman ini tidak ada</h1>
    <p class="text-muted-soft mb-4">
        Alamat yang Anda buka salah, atau kegiatan yang Anda cari sudah diarsipkan
        oleh pengelola. Coba kembali lewat pencarian di bawah.
    </p>

    <form method="GET" action="{{ route('events.index') }}" class="mb-4">
        <div class="input-group">
            <label class="visually-hidden" for="q">Cari kegiatan</label>
            <input type="search" id="q" name="q" class="form-control" placeholder="Cari kegiatan&hellip;">
            <button type="submit" class="btn btn-primary">Cari</button>
        </div>
    </form>

    <div class="d-flex gap-2 justify-content-center flex-wrap">
        <a href="{{ route('home') }}" class="btn btn-outline-secondary">Kembali ke beranda</a>
        <a href="{{ route('events.index') }}" class="btn btn-outline-primary">Jelajahi kegiatan</a>
    </div>
</div>
@endsection
