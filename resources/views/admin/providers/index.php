<?php
/** @var App\Core\Paginator $providers */
/** @var array<string, mixed> $filters */
/** @var list<string> $statuses */
$paginator = $providers;
$statusLabels = ['pending' => 'Menunggu', 'verified' => 'Terverifikasi', 'rejected' => 'Ditolak'];
$statusClasses = ['pending' => 'status-soon', 'verified' => 'status-open', 'rejected' => 'status-closed'];
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Admin</p>
        <h1>Penyelenggara</h1>
        <p class="muted-note">Tinjau profil organisasi dan tetapkan status verifikasi.</p>
    </div>
</div>

<?php require __DIR__ . '/../_nav.php'; ?>
<?php require dirname(__DIR__, 2) . '/partials/form-errors.php'; ?>

<form class="filter-panel" method="get" action="<?= e(url('/admin/penyedia')) ?>">
    <label for="q">Kata kunci</label>
    <input id="q" name="q" type="search" value="<?= e($filters['q'] ?? '') ?>" placeholder="Organisasi, kampus, atau email">

    <label for="status">Status</label>
    <select id="status" name="status">
        <option value="">Semua status</option>
        <?php foreach ($statuses as $status): ?>
            <option value="<?= e($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= e($statusLabels[$status] ?? $status) ?></option>
        <?php endforeach; ?>
    </select>

    <button type="submit" class="button">Terapkan</button>
    <a href="<?= e(url('/admin/penyedia')) ?>">Reset</a>
</form>

<p class="result-count"><?= (int) $providers->total ?> penyelenggara ditemukan.</p>

<?php if ($providers->items === []): ?>
    <p class="empty-state">Tidak ada penyelenggara yang cocok.</p>
<?php else: ?>
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Organisasi</th>
                    <th>Kampus</th>
                    <th>Akun</th>
                    <th>Status</th>
                    <th>Diverifikasi</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($providers->items as $row): ?>
                    <tr>
                        <td>
                            <strong><?= e($row['organization_name']) ?></strong>
                            <div class="admin-meta"><?= e($row['official_email']) ?></div>
                        </td>
                        <td><?= e($row['campus_name']) ?></td>
                        <td><?= e($row['user_name']) ?><br><span class="admin-meta"><?= e($row['user_email']) ?></span></td>
                        <td>
                            <span class="status-badge <?= e($statusClasses[(string) $row['verification_status']] ?? 'status-unknown') ?>">
                                <?= e($statusLabels[(string) $row['verification_status']] ?? $row['verification_status']) ?>
                            </span>
                        </td>
                        <td><?= $row['verified_at'] !== null ? e((new DateTimeImmutable((string) $row['verified_at']))->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('d M Y')) : '-' ?></td>
                        <td><a class="button button-small button-secondary" href="<?= e(url('/admin/penyedia/' . $row['id'])) ?>">Ubah</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php require dirname(__DIR__, 2) . '/partials/pagination.php'; ?>
<?php endif; ?>
