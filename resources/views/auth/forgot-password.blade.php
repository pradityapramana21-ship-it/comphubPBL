@extends('layouts.auth')

@section('title', 'Atur Ulang Kata Sandi')

@section('content')
<div class="card">
    <div class="card__body">
        <h1 class="h4 mb-1">Atur ulang kata sandi</h1>
        <p class="small text-muted-soft mb-4">
            Masukkan email Anda. Jika email itu terdaftar, kami mengirim tautan untuk
            membuat kata sandi baru.
        </p>

        <form method="POST" action="{{ route('auth.password.email') }}">
            @csrf

            @include('partials.form-field', [
                'name' => 'email',
                'label' => 'Email',
                'type' => 'email',
                'required' => true,
                'autocomplete' => 'email',
            ])

            <button type="submit" class="btn btn-primary w-100">Kirim tautan</button>

            <p class="small text-muted-soft text-center mt-3 mb-0">
                <a href="{{ route('auth.login') }}">Kembali ke halaman masuk</a>
            </p>
        </form>
    </div>
</div>
@endsection
