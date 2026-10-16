@extends('layouts.auth')

@section('title', 'Buat Kata Sandi Baru')

@section('content')
<div class="card">
    <div class="card__body">
        <h1 class="h4 mb-1">Buat kata sandi baru</h1>
        <p class="small text-muted-soft mb-4">
            Setelah diganti, semua sesi lain akan dikeluarkan dari akun ini.
        </p>

        <form method="POST" action="{{ route('auth.password.update') }}" data-validate>
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            @include('partials.form-field', [
                'name' => 'email',
                'label' => 'Email',
                'type' => 'email',
                'required' => true,
                'value' => $email,
                'readonly' => true,
            ])

            @include('partials.form-field', [
                'name' => 'password',
                'label' => 'Kata sandi baru',
                'type' => 'password',
                'required' => true,
                'hint' => 'Minimal 8 karakter, mengandung huruf dan angka.',
                'autocomplete' => 'new-password',
            ])

            @include('partials.form-field', [
                'name' => 'password_confirmation',
                'label' => 'Ulangi kata sandi baru',
                'type' => 'password',
                'required' => true,
                'autocomplete' => 'new-password',
            ])

            <button type="submit" class="btn btn-primary w-100">Simpan kata sandi</button>
        </form>
    </div>
</div>
@endsection
