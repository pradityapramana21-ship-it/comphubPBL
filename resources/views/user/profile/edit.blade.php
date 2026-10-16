@extends('layouts.app')

@section('title', 'Profil Saya')

@section('content')
<div class="container" style="max-width: 640px;">
    <h1 class="mb-4">Profil saya</h1>

    <form method="POST" action="{{ route('user.profile.update') }}" data-validate>
        @csrf
        @method('PUT')

        <div class="card mb-3">
            <div class="card__body">
                @include('partials.form-field', [
                    'name' => 'name', 'label' => 'Nama lengkap', 'required' => true,
                    'min' => 3, 'value' => $user->name, 'autocomplete' => 'name',
                ])

                @include('partials.form-field', [
                    'name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true,
                    'value' => $user->email, 'autocomplete' => 'email',
                ])
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Simpan perubahan</button>
        <a href="{{ route('user.profile.password.edit') }}" class="btn btn-outline-secondary">Ubah kata sandi</a>
    </form>
</div>
@endsection
