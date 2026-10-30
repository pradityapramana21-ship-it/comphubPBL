<?php
/** @var array<string, mixed>|null $user */
/** @var list<string> $roles */
/** @var list<string> $statuses */
$isEdit = $user !== null;
$roleLabels = ['user' => 'Pengguna', 'provider' => 'Penyelenggara', 'admin' => 'Admin'];
$statusLabels = ['active' => 'Aktif', 'suspended' => 'Ditangguhkan'];
$action = $isEdit ? '/admin/pengguna/' . $user['id'] : '/admin/pengguna';
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Admin</p>
        <h1><?= $isEdit ? 'Ubah pengguna' : 'Tambah pengguna' ?></h1>
        <p class="muted-note">Peran penyelenggara otomatis mendapat profil organisasi yang perlu diverifikasi.</p>
    </div>
    <a class="button button-secondary" href="<?= e(url('/admin/pengguna')) ?>">Kembali</a>
</div>

<?php require __DIR__ . '/../_nav.php'; ?>
<?php require dirname(__DIR__, 2) . '/partials/form-errors.php'; ?>

<form class="admin-form" method="post" action="<?= e(url($action)) ?>">
    <?= csrf_field() ?>

    <div class="admin-form-row">
        <div>
            <label for="name">Nama</label>
            <input id="name" name="name" value="<?= e(old('name', $user['name'] ?? '')) ?>" required maxlength="255">
        </div>
        <div>
            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="<?= e(old('email', $user['email'] ?? '')) ?>" required maxlength="190">
        </div>
    </div>

    <div class="admin-form-row">
        <div>
            <label for="role">Peran</label>
            <select id="role" name="role" required>
                <?php foreach ($roles as $role): ?>
                    <option value="<?= e($role) ?>" <?= old('role', $user['role'] ?? 'user') === $role ? 'selected' : '' ?>>
                        <?= e($roleLabels[$role] ?? $role) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="status">Status akun</label>
            <select id="status" name="status" required>
                <?php foreach ($statuses as $status): ?>
                    <option value="<?= e($status) ?>" <?= old('status', $user['account_status'] ?? 'active') === $status ? 'selected' : '' ?>>
                        <?= e($statusLabels[$status] ?? $status) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <?php if (! $isEdit): ?>
        <div class="admin-form-row">
            <div>
                <label for="password">Kata sandi</label>
                <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password">
            </div>
            <div>
                <label for="password_confirmation">Ulangi kata sandi</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required minlength="8" autocomplete="new-password">
            </div>
        </div>
    <?php endif; ?>

    <div class="admin-actions">
        <button type="submit" class="button">Simpan</button>
        <a class="button button-secondary" href="<?= e(url('/admin/pengguna')) ?>">Batal</a>
    </div>
</form>
