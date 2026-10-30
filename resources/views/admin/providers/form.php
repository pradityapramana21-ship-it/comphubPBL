<?php
/** @var array<string, mixed> $provider */
/** @var list<string> $statuses */
$statusLabels = ['pending' => 'Menunggu', 'verified' => 'Terverifikasi', 'rejected' => 'Ditolak'];
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Admin</p>
        <h1>Ubah penyelenggara</h1>
        <p class="muted-note">Verifikasi membuka profil dan kegiatan penyelenggara di halaman publik.</p>
    </div>
    <a class="button button-secondary" href="<?= e(url('/admin/penyedia')) ?>">Kembali</a>
</div>

<?php require __DIR__ . '/../_nav.php'; ?>
<?php require dirname(__DIR__, 2) . '/partials/form-errors.php'; ?>

<form class="admin-form" method="post" action="<?= e(url('/admin/penyedia/' . $provider['id'])) ?>">
    <?= csrf_field() ?>

    <div class="admin-form-row">
        <div>
            <label for="organization_name">Nama organisasi</label>
            <input id="organization_name" name="organization_name" value="<?= e(old('organization_name', $provider['organization_name'])) ?>" required maxlength="150">
        </div>
        <div>
            <label for="campus_name">Kampus</label>
            <input id="campus_name" name="campus_name" value="<?= e(old('campus_name', $provider['campus_name'])) ?>" required maxlength="150">
        </div>
    </div>

    <div class="admin-form-row">
        <div>
            <label for="official_email">Email resmi</label>
            <input id="official_email" name="official_email" type="email" value="<?= e(old('official_email', $provider['official_email'])) ?>" required maxlength="190">
        </div>
        <div>
            <label for="website_url">Situs web</label>
            <input id="website_url" name="website_url" type="url" value="<?= e(old('website_url', $provider['website_url'] ?? '')) ?>" maxlength="255">
        </div>
    </div>

    <div>
        <label for="description">Deskripsi organisasi</label>
        <textarea id="description" name="description" maxlength="3000"><?= e(old('description', $provider['description'] ?? '')) ?></textarea>
    </div>

    <div class="admin-form-row">
        <div>
            <label for="verification_status">Status verifikasi</label>
            <select id="verification_status" name="verification_status" required>
                <?php foreach ($statuses as $status): ?>
                    <option value="<?= e($status) ?>" <?= old('verification_status', $provider['verification_status']) === $status ? 'selected' : '' ?>>
                        <?= e($statusLabels[$status] ?? $status) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="verification_note">Catatan verifikasi</label>
            <input id="verification_note" name="verification_note" value="<?= e(old('verification_note', $provider['verification_note'] ?? '')) ?>" maxlength="2000">
        </div>
    </div>

    <p class="muted-note">Akun: <?= e($provider['user_name']) ?> (<?= e($provider['user_email']) ?>), status akun <?= e($provider['user_status']) ?>.</p>

    <div class="admin-actions">
        <button type="submit" class="button">Simpan</button>
        <a class="button button-secondary" href="<?= e(url('/admin/penyedia')) ?>">Batal</a>
    </div>
</form>
