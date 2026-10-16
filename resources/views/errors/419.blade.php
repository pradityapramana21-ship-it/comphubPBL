@extends('layouts.app')

@section('title', 'Sesi sudah berakhir')

@section('content')
<div class="empty-state">
    <p class="empty-state__icon" aria-hidden="true">419</p>
    <h1 class="h4 mb-2">Sesi Anda sudah berakhir</h1>
    <p class="text-muted-soft mb-4">
        Halaman ini terbuka terlalu lama, sehingga token keamanan sudah tidak berlaku.
        Yang Anda ketik tidak hilang dari browser, tapi perlu dikirim ulang sekali lagi.
    </p>

    <div class="d-flex gap-2 justify-content-center flex-wrap">
        @auth
            <a href="{{ route('user.dashboard') }}" class="btn btn-primary">Muat ulang dasbor</a>
        @else
            <a href="{{ route('home') }}" class="btn btn-primary">Kembali ke beranda</a>
        @endauth
    </div>
</div>
@endsection
