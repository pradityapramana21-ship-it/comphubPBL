@extends('layouts.auth')

@section('title', 'Daftar sebagai Penyelenggara')

@section('content')
<div class="card">
    <div class="card__body">
        <h1 class="h4 mb-1">Daftar sebagai penyelenggara</h1>
        <p class="small text-muted-soft mb-4">
            Untuk organisasi yang ingin menerbitkan kegiatan: BEM, himpunan, unit kegiatan,
            fakultas, atau griego dari luar kampus.
        </p>

        <form method="POST" action="{{ route('auth.registerProvider.store') }}"
              enctype="multipart/form-data" data-validate>
            @csrf

            <div class="d-none" aria-hidden="true">
                <label for="website">Website</label>
                <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
            </div>

            <h2 class="sidebar__label mt-2 mb-3">Akun Anda</h2>

            @include('partials.form-field', [
                'name' => 'name', 'label' => 'Nama lengkap', 'required' => true,
                'min' => 3, 'autocomplete' => 'name',
            ])

            @include('partials.form-field', [
                'name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true,
                'autocomplete' => 'email', 'placeholder' => 'nama@kampus.ac.id',
            ])

            @include('partials.form-field', [
                'name' => 'password', 'label' => 'Kata sandi', 'type' => 'password',
                'required' => true, 'hint' => 'Minimal 8 karakter, mengandung huruf dan angka.',
                'autocomplete' => 'new-password',
            ])

            @include('partials.form-field', [
                'name' => 'password_confirmation', 'label' => 'Ulangi kata sandi',
                'type' => 'password', 'required' => true, 'autocomplete' => 'new-password',
            ])

            <hr class="my-4">

            <h2 class="sidebar__label mb-3">Data organisasi</h2>

            @include('partials.form-field', [
                'name' => 'organization_name', 'label' => 'Nama organisasi',
                'required' => true, 'min' => 3,
                'placeholder' => 'mis. BEM Universitas Contoh',
            ])

            @include('partials.form-field', [
                'name' => 'campus_name', 'label' => 'Kampus atau asosiasi',
                'required' => true, 'min' => 3,
                'placeholder' => 'mis. Universitas Contoh',
            ])

            @include('partials.form-field', [
                'name' => 'official_email', 'label' => 'Email resmi organisasi',
                'type' => 'email', 'required' => true,
                'hint' => 'Dipakai admin untuk menghubungi organisasi. Boleh berbeda dari email akun Anda.',
            ])

            @include('partials.form-field', [
                'name' => 'website_url', 'label' => 'Situs organisasi',
                'type' => 'url', 'placeholder' => 'https://organisasi.kampus.ac.id',
                'hint' => 'Opsional. Mempercepat proses verifikasi.',
            ])

            @include('partials.form-field', [
                'name' => 'description', 'label' => 'Profil singkat organisasi',
                'type' => 'textarea', 'rows' => 4, 'maxlength' => 2000,
                'data-counter' => 'hitung-deskripsi',
                'hint' => 'Opsional. Dua kalimat sudah cukup.',
            ])
            <p class="form-hint text-end mb-3" id="hitung-deskripsi">0 / 2000</p>

            <div class="form-field">
                <label for="verification_document" class="form-label">
                    Dokumen pendukung
                    <span class="text-muted-soft">(opsional)</span>
                </label>
                <input type="file" id="verification_document" name="verification_document"
                       class="form-control @error('verification_document') is-invalid @enderror"
                       accept=".pdf,.jpg,.jpeg,.png"
                       @error('verification_document') aria-describedby="dokumen-error" @enderror>
                <p class="form-hint">PDF, JPG, atau PNG. Maksimal 2 MB.</p>
                @error('verification_document')
                    <p class="form-error" id="dokumen-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="alert alert-info small" role="note">
                <p class="mb-0">
                    Dokumen bersifat opsional. Admin mungkin memintanya bila nama
                    organisasi tidak bisa dipastikan dari situs dan email resmi saja.
                </p>
            </div>

            <button type="submit" class="btn btn-primary w-100">Daftar dan ajukan verifikasi</button>

            <p class="small text-muted-soft text-center mt-3 mb-0">
                Sudah punya akun? <a href="{{ route('auth.login') }}">Masuk</a>
            </p>
        </form>
    </div>
</div>
@endsection
