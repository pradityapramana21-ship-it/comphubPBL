@extends('layouts.app')

@section('title', 'Terlalu banyak permintaan')

@section('content')
<div class="empty-state">
    <p class="empty-state__icon" aria-hidden="true">429</p>
    <h1 class="h4 mb-2">Terlalu banyak permintaan dari alamat ini</h1>
    <p class="text-muted-soft mb-4">
        Untuk melindungi akun orang, sistem berhenti menerima permintaan
        dari alamat ini selama beberapa saat. Ini juga berlaku kalau Anda
        baru mencoba beberapa kali saja.
    </p>
    <p class="small text-muted-soft mb-4">
        Tunggu sekitar satu menit, lalu coba lagi. Kalau ini terus terjadi
        padahal Anda tidak sering mencoba masuk, hubungi pengelola.
    </p>

    <a href="{{ route('home') }}" class="btn btn-primary">Kembali ke beranda</a>
</div>
@endsection
