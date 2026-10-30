<?php
/** @var App\Core\Paginator $logs */
/** @var array<string, mixed> $filters */
$paginator = $logs;
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Admin</p>
        <h1>Audit</h1>
        <p class="muted-note">Jejak aksi administratif: pembuatan, perubahan, dan penghapusan data.</p>
    </div>
</div>

<?php require __DIR__ . '/../_nav.php'; ?>

<form class="filter-panel" method="get" action="<?= e(url('/admin/audit')) ?>">
    <label for="action">Aksi</label>
    <input id="action" name="action" value="<?= e($filters['action'] ?? '') ?>" placeholder="user.update">

    <label for="target_type">Jenis target</label>
    <select id="target_type" name="target_type">
        <option value="">Semua target</option>
        <?php foreach (['user', 'event', 'provider', 'report'] as $type): ?>
            <option value="<?= e($type) ?>" <?= ($filters['target_type'] ?? '') === $type ? 'selected' : '' ?>><?= e($type) ?></option>
        <?php endforeach; ?>
    </select>

    <button type="submit" class="button">Terapkan</button>
    <a href="<?= e(url('/admin/audit')) ?>">Reset</a>
</form>

<p class="result-count"><?= (int) $logs->total ?> catatan ditemukan.</p>

<?php if ($logs->items === []): ?>
    <p class="empty-state">Belum ada catatan audit.</p>
<?php else: ?>
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Aktor</th>
                    <th>Aksi</th>
                    <th>Target</th>
                    <th>Metadata</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs->items as $log): ?>
                    <tr>
                        <td><?= e((new DateTimeImmutable((string) $log['created_at']))->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('d M Y H:i:s')) ?></td>
                        <td><?= e($log['actor_role'] ?? '-') ?> #<?= (int) ($log['actor_user_id'] ?? 0) ?></td>
                        <td><code><?= e($log['action']) ?></code></td>
                        <td><?= e($log['target_type'] ?? '-') ?> #<?= (int) ($log['target_id'] ?? 0) ?></td>
                        <td><span class="admin-meta"><?= e(mb_substr((string) ($log['metadata'] ?? ''), 0, 180)) ?></span></td>
                        <td><?= e($log['ip_address'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php require dirname(__DIR__, 2) . '/partials/pagination.php'; ?>
<?php endif; ?>
