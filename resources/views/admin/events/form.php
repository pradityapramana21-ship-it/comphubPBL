<?php
/** @var array<string, mixed>|null $event */
/** @var array<string, string> $values */
/** @var list<array<string, mixed>> $categories */
/** @var list<array<string, mixed>> $providers */
/** @var list<string> $statuses */
$isEdit = $event !== null;
$action = $isEdit ? '/admin/kegiatan/' . $event['id'] : '/admin/kegiatan';
$statusLabels = [
    'draft' => 'Draf',
    'pending' => 'Menunggu peninjauan',
    'changes_requested' => 'Perlu revisi',
    'approved' => 'Tayang',
    'rejected' => 'Ditolak',
    'expired' => 'Kedaluwarsa',
    'archived' => 'Diarsipkan',
];
$deadlineValue = old('deadline_confirmed', $event['deadline_confirmed'] ?? true);
$deadlineChecked = $deadlineValue === true || $deadlineValue === 1 || $deadlineValue === '1' || $deadlineValue === 'on';
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Admin</p>
        <h1><?= $isEdit ? 'Ubah kegiatan' : 'Tambah kegiatan' ?></h1>
        <p class="muted-note">Sumber resmi wajib diisi. Status tayang hanya boleh untuk kegiatan yang sudah diperiksa.</p>
    </div>
    <a class="button button-secondary" href="<?= e(url('/admin/kegiatan')) ?>">Kembali</a>
</div>

<?php require __DIR__ . '/../_nav.php'; ?>
<?php require dirname(__DIR__, 2) . '/partials/form-errors.php'; ?>

<form class="admin-form" method="post" action="<?= e(url($action)) ?>">
    <?= csrf_field() ?>

    <div class="admin-form-row">
        <div>
            <label for="title">Judul</label>
            <input id="title" name="title" value="<?= e(old('title', $event['title'] ?? '')) ?>" required maxlength="180">
        </div>
        <div>
            <label for="slug">Slug</label>
            <input id="slug" name="slug" value="<?= e(old('slug', $event['slug'] ?? '')) ?>" maxlength="200" placeholder="Kosongkan untuk dibuat otomatis">
        </div>
    </div>

    <div class="admin-form-row">
        <div>
            <label for="provider_profile_id">Penyelenggara</label>
            <select id="provider_profile_id" name="provider_profile_id" required>
                <option value="">Pilih penyelenggara</option>
                <?php foreach ($providers as $provider): ?>
                    <option value="<?= (int) $provider['id'] ?>" <?= (string) old('provider_profile_id', $event['provider_profile_id'] ?? '') === (string) $provider['id'] ? 'selected' : '' ?>>
                        <?= e($provider['organization_name']) ?> (<?= e($provider['user_email']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="category_id">Topik</label>
            <select id="category_id" name="category_id" required>
                <option value="">Pilih topik</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['id'] ?>" <?= (string) old('category_id', $event['category_id'] ?? '') === (string) $category['id'] ? 'selected' : '' ?>>
                        <?= e($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="event_type">Jenis</label>
            <select id="event_type" name="event_type">
                <option value="">Belum ditentukan</option>
                <?php foreach (['competition' => 'Lomba', 'webinar' => 'Webinar', 'seminar' => 'Seminar'] as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= old('event_type', $event['event_type'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="admin-form-row">
        <div>
            <label for="organizer_name">Nama penyelenggara di kegiatan</label>
            <input id="organizer_name" name="organizer_name" value="<?= e(old('organizer_name', $event['organizer_name'] ?? '')) ?>" required maxlength="180">
        </div>
        <div>
            <label for="status">Status</label>
            <select id="status" name="status" required>
                <?php foreach ($statuses as $status): ?>
                    <option value="<?= e($status) ?>" <?= old('status', $event['status'] ?? 'draft') === $status ? 'selected' : '' ?>>
                        <?= e($statusLabels[$status] ?? $status) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="admin-form-row">
        <div>
            <label for="organizer_email">Email penyelenggara</label>
            <input id="organizer_email" name="organizer_email" type="email" maxlength="190" value="<?= e(old('organizer_email', $event['organizer_email'] ?? '')) ?>">
        </div>
        <div>
            <label for="contact_number">Nomor kontak</label>
            <input id="contact_number" name="contact_number" maxlength="40" value="<?= e(old('contact_number', $event['contact_number'] ?? '')) ?>">
        </div>
    </div>

    <div>
        <label for="description">Deskripsi</label>
        <textarea id="description" name="description" required minlength="20" maxlength="8000"><?= e(old('description', $event['description'] ?? '')) ?></textarea>
    </div>

    <div>
        <label for="requirements">Persyaratan</label>
        <textarea id="requirements" name="requirements" maxlength="3000"><?= e(old('requirements', $event['requirements'] ?? '')) ?></textarea>
    </div>

    <div class="admin-form-row">
        <div>
            <label for="registration_url">Tautan pendaftaran</label>
            <input id="registration_url" name="registration_url" type="url" value="<?= e(old('registration_url', $event['registration_url'] ?? '')) ?>" required maxlength="500">
        </div>
        <div>
            <label for="source_url">Tautan sumber resmi</label>
            <input id="source_url" name="source_url" type="url" value="<?= e(old('source_url', $event['source_url'] ?? '')) ?>" required maxlength="500">
        </div>
    </div>

    <div class="admin-form-row">
        <div>
            <label for="source_checked_at">Tanggal sumber diperiksa</label>
            <input id="source_checked_at" name="source_checked_at" type="date" value="<?= e(old('source_checked_at', $values['source_checked_at'] ?? '')) ?>" required>
        </div>
        <div>
            <label for="source_note">Catatan sumber</label>
            <input id="source_note" name="source_note" value="<?= e(old('source_note', $event['source_note'] ?? '')) ?>" maxlength="2000">
        </div>
    </div>

    <div class="admin-form-row">
        <div>
            <label for="mode">Mode</label>
            <select id="mode" name="mode" required>
                <?php foreach (['online' => 'Online', 'offline' => 'Offline', 'hybrid' => 'Hybrid'] as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= old('mode', $event['mode'] ?? 'online') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="location_text">Lokasi</label>
            <input id="location_text" name="location_text" value="<?= e(old('location_text', $event['location_text'] ?? '')) ?>" maxlength="255" placeholder="Wajib untuk offline atau hybrid">
        </div>
    </div>

    <div>
        <label for="platform_url">Tautan platform daring</label>
        <input id="platform_url" name="platform_url" type="url" value="<?= e(old('platform_url', $event['platform_url'] ?? '')) ?>" maxlength="500">
    </div>

    <div class="admin-form-row">
        <div>
            <label for="event_start_at">Mulai</label>
            <input id="event_start_at" name="event_start_at" type="datetime-local" value="<?= e(old('event_start_at', $values['event_start_at'] ?? '')) ?>" required>
        </div>
        <div>
            <label for="event_end_at">Selesai</label>
            <input id="event_end_at" name="event_end_at" type="datetime-local" value="<?= e(old('event_end_at', $values['event_end_at'] ?? '')) ?>" required>
        </div>
    </div>

    <div class="admin-form-row">
        <div>
            <label for="registration_start_at">Pendaftaran dibuka</label>
            <input id="registration_start_at" name="registration_start_at" type="datetime-local" value="<?= e(old('registration_start_at', $values['registration_start_at'] ?? '')) ?>">
        </div>
        <div>
            <label for="registration_deadline">Tenggat pendaftaran</label>
            <input id="registration_deadline" name="registration_deadline" type="datetime-local" value="<?= e(old('registration_deadline', $values['registration_deadline'] ?? '')) ?>" required>
        </div>
        <div class="admin-checkbox-field">
            <input type="hidden" name="deadline_confirmed" value="0">
            <label class="checkbox" for="deadline_confirmed">
                <input id="deadline_confirmed" name="deadline_confirmed" type="checkbox" value="1" <?= $deadlineChecked ? 'checked' : '' ?>>
                Tenggat sudah dipastikan
            </label>
        </div>
    </div>

    <div>
        <label for="rejection_reason">Alasan penolakan atau permintaan perubahan</label>
        <textarea id="rejection_reason" name="rejection_reason" maxlength="2000"><?= e(old('rejection_reason', $event['rejection_reason'] ?? '')) ?></textarea>
    </div>

    <div class="admin-actions">
        <button type="submit" class="button">Simpan</button>
        <a class="button button-secondary" href="<?= e(url('/admin/kegiatan')) ?>">Batal</a>
    </div>
</form>

<?php if ($isEdit): ?>
    <form class="admin-danger-zone" method="post" action="<?= e(url('/admin/kegiatan/' . $event['id'] . '/hapus')) ?>" onsubmit="return confirm('Arsipkan kegiatan ini? Kegiatan hilang dari publik, tetapi data dan simpanan tetap utuh.')">
        <?= csrf_field() ?>
        <button type="submit" class="button button-danger">Arsipkan kegiatan</button>
    </form>
<?php endif; ?>
