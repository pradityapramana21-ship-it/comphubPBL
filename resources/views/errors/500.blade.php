@extends('layouts.app')

@section('title', 'Terjadi kesalahan di sisi kami')

@section('content')
<div class="empty-state">
    <p class="empty-state__icon" aria-hidden="true">500</p>
    <h1 class="h4 mb-2">Terjadi kesalahan di sisi kami</h1>
    <p class="text-muted-soft mb-4">
        Ini bukan kesalahan Anda. Tim kami sudah diberi tahu. Coba beberapa saat lagi,
        atau kembali ke beranda sambil kami menelusuri penyebabnya.
    </p>

    <a href="{{ route('home') }}" class="btn btn-primary">Kembali ke beranda</a>
</div>
@endsection
