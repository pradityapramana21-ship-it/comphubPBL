{{--
    Form kegiatan, dipakai bersama oleh halaman buat dan sunting.

    Ini sengaja dipisah supaya keduanya tidak pernah berbeda: kalau ada
    perubahan aturan, cukup di satu tempat.
--}}
@php
    $event = $event ?? null;
    $isEdit = $event !== null;
    $modes = collect(\App\Enums\EventMode::cases())->mapWithKeys(fn($m) => [$m->value => $m->label()]);
    $categoryOptions = $categories->mapWithKeys(fn($c) => [$c->id => $c->name]);
@endphp

<div class="card mb-3">
    <div class="card__body">
        <h2 class="sidebar__label mb-3">Informasi dasar</h2>

        @include('partials.form-field', [
            'name' => 'title', 'label' => 'Judul kegiatan', 'required' => true, 'maxlength' => 180,
            'min' => 15, 'value' => $event?->title,
            'placeholder' => 'mis. Lomba Pemrograman Kompetitif 2026',
            'hint' => 'Spesifik. "Lomba" saja tidak akan lolos pemeriksaan.',
        ])

        @include('partials.form-field', [
            'name' => 'category_id', 'label' => 'Kategori', 'type' => 'select', 'required' => true,
            'options' => $categoryOptions->all(),
            'value' => $event?->category_id,
            'placeholder' => 'Pilih kategori',
        ])

        @include('partials.form-field', [
            'name' => 'description', 'label' => 'Deskripsi', 'type' => 'textarea', 'required' => true,
            'min' => 120, 'maxlength' => 8000, 'rows' => 6, 'value' => $event?->description,
            'data-counter' => 'hitung-deskripsi',
            'hint' => 'Minimal 120 karakter. Sebutkan apa kegiatannya, siapa sasarannya, dan apa yang didapat peserta.',
        ])
        <p class="form-hint text-end mb-0" id="hitung-deskripsi">{{ strlen((string) old('description', $event?->description)) }} / 8000</p>

        @include('partials.form-field', [
            'name' => 'requirements', 'label' => 'Syarat peserta', 'type' => 'textarea',
            'maxlength' => 3000, 'rows' => 4, 'value' => $event?->requirements,
            'hint' => 'Opsional.',
        ])
    </div>
</div>

<div class="card mb-3">
    <div class="card__body">
        <h2 class="sidebar__label mb-3">Waktu</h2>

        <div class="row g-2">
            <div class="col-md-4">
                @include('partials.form-field', [
                    'name' => 'event_start_at', 'label' => 'Mulai', 'type' => 'datetime-local', 'required' => true,
                    'value' => $event?->event_start_at ? $dateHelper->inputValue($event->event_start_at) : null,
                ])
            </div>
            <div class="col-md-4">
                @include('partials.form-field', [
                    'name' => 'event_end_at', 'label' => 'Selesai', 'type' => 'datetime-local', 'required' => true,
                    'value' => $event?->event_end_at ? $dateHelper->inputValue($event->event_end_at) : null,
                ])
            </div>
            <div class="col-md-4">
                @include('partials.form-field', [
                    'name' => 'registration_deadline', 'label' => 'Tenggat pendaftaran',
                    'type' => 'datetime-local', 'required' => true,
                    'value' => $event?->registration_deadline ? $dateHelper->inputValue($event->registration_deadline) : null,
                    'hint' => 'Tidak boleh melewati waktu mulai.',
                ])
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card__body">
        <h2 class="sidebar__label mb-3">Bentuk dan pendaftaran</h2>

        @include('partials.form-field', [
            'name' => 'mode', 'label' => 'Bentuk kegiatan', 'type' => 'select', 'required' => true,
            'options' => $modes->all(), 'value' => $event?->mode, 'placeholder' => 'Pilih bentuk',
        ])

        @include('partials.form-field', [
            'name' => 'location_text', 'label' => 'Lokasi', 'maxlength' => 255,
            'value' => $event?->location_text,
            'hint' => 'Wajib untuk luring dan hybrid.',
        ])

        @include('partials.form-field', [
            'name' => 'platform_url', 'label' => 'Tautan daring', 'type' => 'url',
            'value' => $event?->platform_url, 'placeholder' => 'https://meet.google.com/...',
            'hint' => 'Wajib untuk kegiatan yang sepenuhnya daring.',
        ])

        @include('partials.form-field', [
            'name' => 'registration_url', 'label' => 'Tautan pendaftaran', 'type' => 'url', 'required' => true,
            'maxlength' => 500, 'value' => $event?->registration_url,
            'placeholder' => 'https://organisasi.kampus.ac.id/daftar',
            'hint' => 'Pakai tautan panjang dari situs resmi, bukan pemendek seperti bit.ly.',
        ])
    </div>
</div>

<div class="d-none" aria-hidden="true">
    <label for="website">Website</label>
    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
</div>
