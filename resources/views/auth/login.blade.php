@extends('layouts.auth')

@section('title', 'Masuk')

@section('content')
<div class="card">
    <div class="card__body">
        <h1 class="h4 mb-1">Masuk</h1>
        <p class="small text-muted-soft mb-4">Gunakan email dan kata sandi Anda.</p>

        <form method="POST" action="{{ route('auth.login.attempt') }}" data-validate>
            @csrf

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
                'autocomplete' => 'current-password',
            ])

            <div class="form-check mb-3">
                <input type="checkbox" id="remember" name="remember" value="1"
                       class="form-check-input" @checked(old('remember'))>
                <label for="remember" class="form-check-label">Ingat saya di perangkat ini</label>
            </div>

            <button type="submit" class="btn btn-primary w-100">Masuk</button>

            <p class="small text-muted-soft text-center mt-3 mb-0">
                <a href="{{ route('auth.password.request') }}">Lupa kata sandi?</a>
            </p>
        </form>
    </div>
</div>

<div class="card mt-3">
    <div class="card__body py-3">
        <p class="small mb-2">Belum punya akun?</p>
        <div class="d-flex gap-2">
            <a href="{{ route('auth.register') }}" class="btn btn-outline-primary btn-sm flex-fill">Sebagai peserta</a>
            <a href="{{ route('auth.registerProvider') }}" class="btn btn-outline-secondary btn-sm flex-fill">Sebagai penyelenggara</a>
        </div>
    </div>
</div>
@endsection
