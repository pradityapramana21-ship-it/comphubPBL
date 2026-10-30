<?php
/** @var App\Core\Paginator $users */
/** @var array<string, mixed> $filters */
/** @var list<string> $roles */
/** @var list<string> $statuses */
$paginator = $users;
$roleLabels = ['user' => 'Pengguna', 'provider' => 'Penyelenggara', 'admin' => 'Admin'];
$statusLabels = ['active' => 'Aktif', 'suspended' => 'Ditangguhkan'];
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Admin</p>
        <h1>Pengguna</h1>
        <p class="muted-note">Kelola peran, status akun, dan data pengguna CompHub.</p>
    </div>
    <a class="button" href="<?= e(url('/admin/pengguna/baru')) ?>">Tambah pengguna</a>
</div>

<?php require __DIR__ . '/../_nav.php'; ?>
<?php require dirname(__DIR__, 2) . '/partials/form-errors.php'; ?>

<form class="filter-panel" method="get" action="<?= e(url('/admin/pengguna')) ?>">
    <label for="q">Kata kunci</label>
    <input id="q" name="q" type="search" value="<?= e($filters['keyword'] ?? '') ?>" placeholder="Nama atau email">

    <label for="role">Peran</label>
    <select id="role" name="role">
        <option value="">Semua peran</option>
        <?php foreach ($roles as $role): ?>
            <option value="<?= e($role) ?>" <?= ($filters['role'] ?? '') === $role ? 'selected' : '' ?>><?= e($roleLabels[$role] ?? $role) ?></option>
        <?php endforeach; ?>
    </select>

    <label for="status">Status</label>
    <select id="status" name="status">
        <option value="">Semua status</option>
        <?php foreach ($statuses as $status): ?>
            <option value="<?= e($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= e($statusLabels[$status] ?? $status) ?></option>
        <?php endforeach; ?>
    </select>

    <button type="submit" class="button">Terapkan</button>
    <a href="<?= e(url('/admin/pengguna')) ?>">Reset</a>
</form>

<p class="result-count"><?= (int) $users->total ?> pengguna ditemukan.</p>

<?php if ($users->items === []): ?>
    <p class="empty-state">Tidak ada pengguna yang cocok.</p>
<?php else: ?>
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Email</th>
                    <th>Peran</th>
                    <th>Status</th>
                    <th>Penyelenggara</th>
                    <th>Dibuat</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users->items as $row): ?>
                    <tr>
                        <td><?= e($row['name']) ?></td>
                        <td><?= e($row['email']) ?></td>
                        <td><?= e($roleLabels[(string) $row['role']] ?? $row['role']) ?></td>
                        <td>
                            <span class="status-badge <?= $row['account_status'] === 'active' ? 'status-open' : 'status-closed' ?>">
                                <?= e($statusLabels[(string) $row['account_status']] ?? $row['account_status']) ?>
                            </span>
                        </td>
                        <td><?= e($row['organization_name'] ?? '-') ?></td>
                        <td><?= e((new DateTimeImmutable((string) $row['created_at']))->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('d M Y')) ?></td>
                        <td>
                            <div class="admin-actions">
                                <a class="button button-small button-secondary" href="<?= e(url('/admin/pengguna/' . $row['id'])) ?>">Ubah</a>
                                <form method="post" action="<?= e(url('/admin/pengguna/' . $row['id'] . '/hapus')) ?>" onsubmit="return confirm('Hapus pengguna ini? Profil penyelenggara, kegiatan, dan penyimpanan terkait ikut terhapus.')">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="button button-small button-danger">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php require dirname(__DIR__, 2) . '/partials/pagination.php'; ?>
<?php endif; ?>
