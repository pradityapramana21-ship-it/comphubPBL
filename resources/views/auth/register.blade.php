@extends('layouts.auth')

@section('title', 'Daftar sebagai Peserta')

@section('content')
<div class="card">
    <div class="card__body">
        <h1 class="h4 mb-1">Daftar sebagai peserta</h1>
        <p class="small text-muted-soft mb-4">
            Untuk mencari, menyimpan, dan mengikuti kegiatan.
        </p>

        <form method="POST" action="{{ route('auth.register.store') }}" data-validate>
            @csrf

            {{-- Honeypot. Field ini tidak pernah dilihat orang, tapi akan
                 terisi kalau form diisi bot. Aturan "max:0" menolaknya. --}}
            <div class="d-none" aria-hidden="true">
                <label for="website">Website</label>
                <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
            </div>

            @include('partials.form-field', [
                'name' => 'name',
                'label' => 'Nama lengkap',
                'required' => true,
                'min' => 3,
                'autocomplete' => 'name',
                'placeholder' => 'Nama sesuai identitas',
            ])

            @include('partials.form-field', [
                'name' => 'email',
                'label' => 'Email',
                'type' => 'email',
                'required' => true,
                'autocomplete' => 'email',
                'placeholder' => 'nama@kampus.ac.id',
            ])

            @include('partials.form-field', [
                'name' => 'password',
                'label' => 'Kata sandi',
                'type' => 'password',
                'required' => true,
                'hint' => 'Minimal 8 karakter, mengandung huruf dan angka.',
                'autocomplete' => 'new-password',
            ])

            @include('partials.form-field', [
                'name' => 'password_confirmation',
                'label' => 'Ulangi kata sandi',
                'type' => 'password',
                'required' => true,
                'autocomplete' => 'new-password',
            ])

            <button type="submit" class="btn btn-primary w-100">Buat akun</button>

            <p class="small text-muted-soft text-center mt-3 mb-0">
                Sudah punya akun? <a href="{{ route('auth.login') }}">Masuk</a>
            </p>
        </form>
    </div>
</div>

<div class="card mt-3">
    <div class="card__body py-3 text-center">
        <p class="small mb-0">
            Organisasi mahasiswa yang ingin menerbitkan kegiatan&mdash;
            <a href="{{ route('auth.registerProvider') }}">daftar sebagai penyelenggara</a>.
        </p>
    </div>
</div>
@endsection
