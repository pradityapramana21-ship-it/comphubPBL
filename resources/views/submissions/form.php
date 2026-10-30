<?php
/** @var array<string, mixed>|null $event */
/** @var string $token */
/** @var bool $revision */
/** @var list<array<string, mixed>> $categories */
$event ??= null;
$token = (string) ($token ?? '');
$revision = (bool) ($revision ?? false);
$action = $revision ? url('/pengajuan/' . $token) : url('/ajukan');
$value = static function (string $key, string $default = '') use ($event): string {
    $current = old($key, is_array($event) ? ($event[$key] ?? $default) : $default);

    return is_scalar($current) ? (string) $current : $default;
};
$local = static function (string $key) use ($event): string {
    $current = old($key, is_array($event) ? ($event[$key] ?? '') : '');

    if (! is_string($current) || trim($current) === '') {
        return '';
    }

    try {
        return (new DateTimeImmutable($current))->format('Y-m-d\TH:i');
    } catch (Exception) {
        return '';
    }
};
?>
<section class="submission-shell">
    <div class="submission-head">
        <p class="eyebrow">Untuk penyelenggara</p>
        <h1><?= $revision ? 'Perbaiki pengajuan' : 'Ajukan kegiatan' ?></h1>
        <p class="muted-note">Tidak perlu akun. Isi data resmi kegiatan; tim CompHub akan meninjau sebelum tayang.</p>
    </div>

    <?php if ($revision && is_array($event)): ?>
        <div class="notice">
            <strong>Admin meminta perbaikan.</strong>
            <?php if (trim((string) ($event['rejection_reason'] ?? '')) !== ''): ?>
                <p><?= e($event['rejection_reason']) ?></p>
            <?php endif; ?>
            <p>Tautan ini hanya berlaku sampai batas waktu yang ditentukan dan menjadi tidak aktif setelah formulir dikirim.</p>
        </div>
    <?php endif; ?>

    <?php require dirname(__DIR__) . '/partials/form-errors.php'; ?>

    <form class="submission-form" method="post" action="<?= e($action) ?>" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>
        <div class="honeypot" aria-hidden="true">
            <label for="website">Situs web</label>
            <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
        </div>

        <fieldset>
            <legend>Informasi kegiatan</legend>
            <label for="event_type">Jenis kegiatan</label>
            <select id="event_type" name="event_type" required>
                <?php foreach (['competition' => 'Lomba', 'webinar' => 'Webinar', 'seminar' => 'Seminar'] as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $value('event_type', 'competition') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="title">Judul kegiatan</label>
            <input id="title" name="title" type="text" maxlength="180" required value="<?= e($value('title')) ?>">

            <label for="category_id">Topik</label>
            <select id="category_id" name="category_id" required>
                <option value="">Pilih topik</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['id'] ?>" <?= $value('category_id') === (string) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="description">Deskripsi</label>
            <textarea id="description" name="description" rows="5" required minlength="40"><?= e($value('description')) ?></textarea>

            <label for="requirements">Syarat peserta <span class="muted-note">(opsional)</span></label>
            <textarea id="requirements" name="requirements" rows="3"><?= e($value('requirements')) ?></textarea>
        </fieldset>

        <fieldset>
            <legend>Penyelenggara</legend>
            <label for="organizer_name">Nama penyelenggara</label>
            <input id="organizer_name" name="organizer_name" type="text" maxlength="180" required value="<?= e($value('organizer_name')) ?>">

            <label for="organizer_email">Email resmi</label>
            <input id="organizer_email" name="organizer_email" type="email" maxlength="190" required value="<?= e($value('organizer_email')) ?>">

            <label for="contact_number">Nomor kontak</label>
            <input id="contact_number" name="contact_number" type="text" maxlength="40" required value="<?= e($value('contact_number')) ?>">
        </fieldset>

        <fieldset>
            <legend>Jadwal dan lokasi</legend>
            <label for="mode">Mode</label>
            <select id="mode" name="mode" required>
                <?php foreach (['online' => 'Online', 'offline' => 'Offline', 'hybrid' => 'Hybrid'] as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $value('mode', 'offline') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="location_text">Lokasi</label>
            <input id="location_text" name="location_text" type="text" maxlength="255" value="<?= e($value('location_text')) ?>">

            <label for="platform_url">Tautan platform <span class="muted-note">(opsional)</span></label>
            <input id="platform_url" name="platform_url" type="url" maxlength="500" value="<?= e($value('platform_url')) ?>">

            <label for="registration_start_at">Pendaftaran dibuka</label>
            <input id="registration_start_at" name="registration_start_at" type="datetime-local" value="<?= e($local('registration_start_at')) ?>">

            <label for="registration_deadline">Tenggat pendaftaran</label>
            <input id="registration_deadline" name="registration_deadline" type="datetime-local" required value="<?= e($local('registration_deadline')) ?>">

            <label for="event_start_at">Mulai kegiatan</label>
            <input id="event_start_at" name="event_start_at" type="datetime-local" required value="<?= e($local('event_start_at')) ?>">

            <label for="event_end_at">Selesai kegiatan</label>
            <input id="event_end_at" name="event_end_at" type="datetime-local" required value="<?= e($local('event_end_at')) ?>">
        </fieldset>

        <fieldset>
            <legend>Tautan dan berkas</legend>
            <label for="registration_url">Tautan pendaftaran resmi</label>
            <input id="registration_url" name="registration_url" type="url" maxlength="500" required value="<?= e($value('registration_url')) ?>">

            <label for="source_url">Tautan sumber <span class="muted-note">(opsional)</span></label>
            <input id="source_url" name="source_url" type="url" maxlength="500" value="<?= e($value('source_url')) ?>">

            <label for="poster">Poster <?= $revision ? '<span class="muted-note">(kosongkan jika tidak diganti)</span>' : '' ?></label>
            <input id="poster" name="poster" type="file" accept="image/jpeg,image/png,image/webp" <?= $revision ? '' : 'required' ?>>
            <p class="field-help">JPEG, PNG, atau WebP. Maksimal 2 MB.</p>
            <?php if ($revision && trim((string) ($event['poster_path'] ?? '')) !== ''): ?>
                <p class="field-help">Poster saat ini sudah tersimpan.</p>
            <?php endif; ?>

            <label for="guidebook">Panduan PDF <span class="muted-note">(wajib untuk lomba)</span></label>
            <input id="guidebook" name="guidebook" type="file" accept="application/pdf">
            <p class="field-help">PDF maksimal 5 MB.</p>
            <?php if ($revision && trim((string) ($event['guidebook_path'] ?? '')) !== ''): ?>
                <p class="field-help">Panduan saat ini sudah tersimpan.</p>
            <?php endif; ?>
        </fieldset>

        <button type="submit" class="button"><?= $revision ? 'Kirim perbaikan' : 'Kirim pengajuan' ?></button>
    </form>
</section>
