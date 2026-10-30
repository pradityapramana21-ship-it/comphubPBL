<?php
/** @var App\Core\Paginator $events */
/** @var App\DataTransferObjects\CatalogFilter $filter */
/** @var list<array<string, mixed>> $categories */
/** @var list<string> $statuses */
$paginator = $events;
$statusLabels = [
    'draft' => 'Draf',
    'pending' => 'Menunggu',
    'changes_requested' => 'Perlu revisi',
    'approved' => 'Tayang',
    'rejected' => 'Ditolak',
    'expired' => 'Kedaluwarsa',
    'archived' => 'Diarsipkan',
];
$statusClasses = [
    'approved' => 'status-open',
    'pending' => 'status-soon',
    'changes_requested' => 'status-soon',
    'rejected' => 'status-closed',
    'expired' => 'status-closed',
    'archived' => 'status-unknown',
    'draft' => 'status-unknown',
];
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Admin</p>
        <h1>Kegiatan</h1>
        <p class="muted-note">Tambah, ubah, tinjau status, dan arsipkan kegiatan. Hapus permanen tidak tersedia.</p>
    </div>
    <a class="button" href="<?= e(url('/admin/kegiatan/baru')) ?>">Tambah kegiatan</a>
</div>

<?php require __DIR__ . '/../_nav.php'; ?>
<?php require dirname(__DIR__, 2) . '/partials/form-errors.php'; ?>

<form class="filter-panel" method="get" action="<?= e(url('/admin/kegiatan')) ?>">
    <label for="q">Kata kunci</label>
    <input id="q" name="q" type="search" value="<?= e($filter->q) ?>">

    <label for="category">Kategori</label>
    <select id="category" name="category">
        <option value="">Semua kategori</option>
        <?php foreach ($categories as $category): ?>
            <option value="<?= e($category['slug']) ?>" <?= $filter->category === $category['slug'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
        <?php endforeach; ?>
    </select>

    <label for="mode">Mode</label>
    <select id="mode" name="mode">
        <option value="">Semua mode</option>
        <?php foreach (['online' => 'Online', 'offline' => 'Offline', 'hybrid' => 'Hybrid'] as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $filter->mode === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>

    <label for="status">Status</label>
    <select id="status" name="status">
        <option value="">Semua status</option>
        <?php foreach ($statuses as $status): ?>
            <option value="<?= e($status) ?>" <?= $filter->status === $status ? 'selected' : '' ?>><?= e($statusLabels[$status] ?? $status) ?></option>
        <?php endforeach; ?>
    </select>

    <button type="submit" class="button">Terapkan</button>
    <a href="<?= e(url('/admin/kegiatan')) ?>">Reset</a>
</form>

<p class="result-count"><?= (int) $events->total ?> kegiatan ditemukan.</p>

<?php if ($events->items === []): ?>
    <p class="empty-state">Tidak ada kegiatan yang cocok.</p>
<?php else: ?>
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Judul</th>
                    <th>Penyelenggara</th>
                    <th>Kategori</th>
                    <th>Status</th>
                    <th>Tenggat</th>
                    <th>Statistik</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($events->items as $row): ?>
                    <tr>
                        <td>
                            <a href="<?= e(url('/admin/kegiatan/' . $row['id'])) ?>"><?= e($row['title']) ?></a>
                            <div class="admin-meta"><?= e($row['slug']) ?></div>
                        </td>
                        <td><?= e($row['organization_name']) ?></td>
                        <td><?= e($row['category_name']) ?></td>
                        <td>
                            <span class="status-badge <?= e($statusClasses[(string) $row['status']] ?? 'status-unknown') ?>">
                                <?= e($statusLabels[(string) $row['status']] ?? $row['status']) ?>
                            </span>
                        </td>
                        <td><?= e((new DateTimeImmutable((string) $row['registration_deadline']))->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('d M Y H:i')) ?></td>
                        <td><?= (int) $row['view_count'] ?> dilihat<br><?= (int) $row['bookmark_count'] ?> disimpan</td>
                        <td>
                            <div class="admin-actions">
                                <a class="button button-small button-secondary" href="<?= e(url('/admin/kegiatan/' . $row['id'])) ?>">Ubah</a>
                                <form method="post" action="<?= e(url('/admin/kegiatan/' . $row['id'] . '/hapus')) ?>" onsubmit="return confirm('Arsipkan kegiatan ini? Data dan simpanan tetap utuh.')">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="button button-small button-danger">Arsipkan</button>
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
