@extends('layouts.app')

@section('title', 'Akses ditolak')

@section('content')
<div class="empty-state">
    <p class="empty-state__icon" aria-hidden="true">403</p>
    <h1 class="h4 mb-2">Anda tidak punya akses ke halaman ini</h1>
    <p class="text-muted-soft mb-4">
        Halaman itu hanya untuk pengguna dengan peran lain, atau datanya memang bukan
        milik Anda. Kalau ini keliru, masuk dengan akun yang benar.
    </p>

    <div class="d-flex gap-2 justify-content-center flex-wrap">
        @auth
            <a href="{{ route('user.dashboard') }}" class="btn btn-outline-secondary">Kembali ke dasbor</a>
        @else
            <a href="{{ route('login') }}" class="btn btn-outline-secondary">Masuk</a>
        @endauth
        <a href="{{ route('events.index') }}" class="btn btn-outline-primary">Jelajahi kegiatan</a>
    </div>
</div>
@endsection
