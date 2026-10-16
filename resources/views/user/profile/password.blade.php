@extends('layouts.app')

@section('title', 'Ubah Kata Sandi')

@section('content')
<div class="container" style="max-width: 640px;">
    <h1 class="mb-1">Ubah kata sandi</h1>
    <p class="text-muted-soft mb-4">
        Setelah diganti, sesi di perangkat lain akan dikeluarkan dari akun ini.
    </p>

    <form method="POST" action="{{ route('user.profile.password.update') }}" data-validate>
        @csrf
        @method('PUT')

        <div class="card mb-3">
            <div class="card__body">
                @include('partials.form-field', [
                    'name' => 'current_password', 'label' => 'Kata sandi saat ini',
                    'type' => 'password', 'required' => true, 'autocomplete' => 'current-password',
                ])

                @include('partials.form-field', [
                    'name' => 'password', 'label' => 'Kata sandi baru', 'type' => 'password',
                    'required' => true, 'hint' => 'Minimal 8 karakter, mengandung huruf dan angka.',
                    'autocomplete' => 'new-password',
                ])

                @include('partials.form-field', [
                    'name' => 'password_confirmation', 'label' => 'Ulangi kata sandi baru',
                    'type' => 'password', 'required' => true, 'autocomplete' => 'new-password',
                ])
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Simpan kata sandi</button>
        <a href="{{ route('user.profile.edit') }}" class="btn btn-outline-secondary">Kembali</a>
    </form>
</div>
@endsection
