@extends('layouts.app')

@section('title', 'Profil Organisasi')

@section('content')
<div class="container" style="max-width: 700px;">
    <h1 class="mb-1">Profil organisasi</h1>
    <p class="text-muted-soft mb-4">
        Data di sini yang diperiksa administrator sebelum kegiatan Anda boleh tayang.
    </p>

    <div class="alert alert-{{ $profile->verification_status->tone() }} d-flex gap-2 align-items-start">
        <svg width="20" height="20" aria-hidden="true" class="flex-shrink-0"><use href="{{ asset('images/icon-sprite.svg') }}#i-shield"></use></svg>
        <div>
            <strong>{{ $profile->verification_status->label() }}</strong>
            <p class="mb-0 small">
                @if ($profile->verification_status->value === 'verified')
                    Diperiksa {{ $dateHelper->date($profile->verified_at) }}. Anda bisa mengajukan kegiatan.
                @elseif ($profile->verification_status->value === 'pending')
                    Data Anda sedang diperiksa administrator. Anda bisa menyimpan draf, tetapi belum bisa mengajukan.
                @else
                    {{ $profile->verification_note ?? 'Verifikasi ditolak.' }} Perbaiki data di bawah, lalu ajukan ulang.
                @endif
            </p>
        </div>
    </div>

    <form method="POST" action="{{ route('provider.profile.update') }}" enctype="multipart/form-data" data-validate>
        @csrf
        @method('PUT')

        <div class="card mb-3">
            <div class="card__body">
                @include('partials.form-field', [
                    'name' => 'organization_name', 'label' => 'Nama organisasi', 'required' => true,
                    'min' => 3, 'value' => $profile->organization_name,
                ])

                @include('partials.form-field', [
                    'name' => 'campus_name', 'label' => 'Kampus atau asosiasi', 'required' => true,
                    'min' => 3, 'value' => $profile->campus_name,
                ])

                @include('partials.form-field', [
                    'name' => 'official_email', 'label' => 'Email resmi organisasi', 'type' => 'email',
                    'required' => true, 'value' => $profile->official_email,
                ])

                @include('partials.form-field', [
                    'name' => 'website_url', 'label' => 'Situs organisasi', 'type' => 'url',
                    'value' => $profile->website_url, 'placeholder' => 'https://organisasi.kampus.ac.id',
                ])

                @include('partials.form-field', [
                    'name' => 'description', 'label' => 'Profil singkat', 'type' => 'textarea',
                    'maxlength' => 2000, 'rows' => 4, 'value' => $profile->description,
                ])
            </div>
        </div>

        @if ($canResubmit)
            <div class="card mb-3 border-danger">
                <div class="card__body">
                    <h2 class="sidebar__label mb-3">Ajukan verifikasi ulang</h2>

                    <div class="form-field">
                        <label for="verification_document" class="form-label">Dokumen pendukung baru</label>
                        <input type="file" id="verification_document" name="verification_document"
                               class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                        <p class="form-hint">PDF, JPG, atau PNG. Maksimal 2 MB. Dokumen lama akan diganti.</p>
                    </div>

                    @include('partials.form-field', [
                        'name' => 'verification_note', 'label' => 'Penjelasan singkat',
                        'type' => 'textarea', 'rows' => 3, 'maxlength' => 500,
                        'hint' => 'Apa yang sudah diperbaiki sejak penolakan pertama?',
                    ])

                    <div class="form-check">
                        <input type="checkbox" id="resubmit" name="resubmit" value="1" class="form-check-input">
                        <label for="resubmit" class="form-check-label">
                            Ajukan kembali ke antrean verifikasi
                        </label>
                    </div>
                </div>
            </div>
        @endif

        <button type="submit" class="btn btn-primary">Simpan data organisasi</button>
    </form>
</div>
@endsection
