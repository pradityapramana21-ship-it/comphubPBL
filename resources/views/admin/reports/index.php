<?php
/** @var App\Core\Paginator $reports */
/** @var array<string, mixed> $filters */
/** @var list<string> $statuses */
/** @var list<string> $reasons */
$paginator = $reports;
$statusLabels = ['open' => 'Terbuka', 'reviewing' => 'Ditinjau', 'resolved' => 'Selesai', 'dismissed' => 'Ditolak'];
$reasonLabels = [
    'misleading_info' => 'Informasi menyesatkan',
    'broken_link' => 'Tautan rusak',
    'spam' => 'Spam',
    'scam' => 'Penipuan',
    'duplicate' => 'Duplikat',
    'other' => 'Lainnya',
];
$statusClasses = ['open' => 'status-soon', 'reviewing' => 'status-soon', 'resolved' => 'status-open', 'dismissed' => 'status-closed'];
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Admin</p>
        <h1>Laporan</h1>
        <p class="muted-note">Tinjau laporan kegiatan dan tetapkan status penyelesaian.</p>
    </div>
</div>

<?php require __DIR__ . '/../_nav.php'; ?>
<?php require dirname(__DIR__, 2) . '/partials/form-errors.php'; ?>

<form class="filter-panel" method="get" action="<?= e(url('/admin/laporan')) ?>">
    <label for="status">Status</label>
    <select id="status" name="status">
        <option value="">Semua status</option>
        <?php foreach ($statuses as $status): ?>
            <option value="<?= e($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= e($statusLabels[$status] ?? $status) ?></option>
        <?php endforeach; ?>
    </select>

    <label for="reason">Alasan</label>
    <select id="reason" name="reason">
        <option value="">Semua alasan</option>
        <?php foreach ($reasons as $reason): ?>
            <option value="<?= e($reason) ?>" <?= ($filters['reason'] ?? '') === $reason ? 'selected' : '' ?>><?= e($reasonLabels[$reason] ?? $reason) ?></option>
        <?php endforeach; ?>
    </select>

    <button type="submit" class="button">Terapkan</button>
    <a href="<?= e(url('/admin/laporan')) ?>">Reset</a>
</form>

<p class="result-count"><?= (int) $reports->total ?> laporan ditemukan.</p>

<?php if ($reports->items === []): ?>
    <p class="empty-state">Belum ada laporan masuk.</p>
<?php else: ?>
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Kegiatan</th>
                    <th>Pelapor</th>
                    <th>Alasan</th>
                    <th>Status</th>
                    <th>Dibuat</th>
                    <th>Tindakan</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reports->items as $row): ?>
                    <tr>
                        <td>
                            <a href="<?= e(url('/admin/kegiatan/' . $row['event_id'])) ?>"><?= e($row['event_title']) ?></a>
                            <?php if (! empty($row['description'])): ?>
                                <div class="admin-meta"><?= e(mb_substr((string) $row['description'], 0, 160)) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= e($row['reporter_name']) ?><br><span class="admin-meta"><?= e($row['reporter_email']) ?></span></td>
                        <td><?= e($reasonLabels[(string) $row['reason_code']] ?? $row['reason_code']) ?></td>
                        <td>
                            <span class="status-badge <?= e($statusClasses[(string) $row['status']] ?? 'status-unknown') ?>">
                                <?= e($statusLabels[(string) $row['status']] ?? $row['status']) ?>
                            </span>
                        </td>
                        <td><?= e((new DateTimeImmutable((string) $row['created_at']))->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('d M Y H:i')) ?></td>
                        <td>
                            <form class="admin-inline-form" method="post" action="<?= e(url('/admin/laporan/' . $row['id'])) ?>">
                                <?= csrf_field() ?>
                                <select name="status" aria-label="Status laporan" required>
                                    <?php foreach ($statuses as $status): ?>
                                        <option value="<?= e($status) ?>" <?= $row['status'] === $status ? 'selected' : '' ?>><?= e($statusLabels[$status] ?? $status) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input name="resolution_note" value="<?= e($row['resolution_note'] ?? '') ?>" maxlength="2000" placeholder="Catatan penyelesaian" aria-label="Catatan penyelesaian">
                                <button type="submit" class="button button-small">Simpan</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php require dirname(__DIR__, 2) . '/partials/pagination.php'; ?>
<?php endif; ?>
