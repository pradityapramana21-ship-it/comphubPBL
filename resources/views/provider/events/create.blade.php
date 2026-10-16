@extends('layouts.app')

@section('title', 'Buat Kegiatan')

@section('content')
<div class="container" style="max-width: 820px;">
    <h1 class="mb-1">Buat kegiatan baru</h1>
    <p class="text-muted-soft mb-4">
        Ini disimpan sebagai <strong>draf</strong> dulu. Draf tidak terlihat publik dan
        belum menjalankan pemeriksaan otomatis&mdash;pemeriksaan berjalan saat kamu menekan Ajukan.
    </p>

    <form method="POST" action="{{ route('provider.events.store') }}" data-validate>
        @csrf
        @include('provider.events._form', ['event' => null, 'categories' => $categories])
        <button type="submit" class="btn btn-primary">Simpan sebagai draf</button>
        <a href="{{ route('provider.events.index') }}" class="btn btn-outline-secondary">Batal</a>
    </form>
</div>
@endsection
