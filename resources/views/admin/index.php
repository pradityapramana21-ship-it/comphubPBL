<?php
/** @var array<string, int> $userCounts */
/** @var array<string, int> $eventStatuses */
/** @var array<string, int> $providerStatuses */
/** @var list<array<string, mixed>> $recentAudit */
$eventLabels = [
    'draft' => 'Draf',
    'pending' => 'Menunggu',
    'changes_requested' => 'Perlu revisi',
    'approved' => 'Tayang',
    'rejected' => 'Ditolak',
    'expired' => 'Kedaluwarsa',
    'archived' => 'Diarsipkan',
];
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Admin</p>
        <h1>Monitoring CompHub</h1>
        <p class="muted-note">Ringkasan data, antrean, dan aktivitas administratif.</p>
    </div>
    <a class="button" href="<?= e(url('/admin/kegiatan/baru')) ?>">Tambah kegiatan</a>
</div>

<?php require __DIR__ . '/_nav.php'; ?>

<div class="stat-grid">
    <a class="stat-card" href="<?= e(url('/admin/pengguna')) ?>">
        <span class="stat-value"><?= (int) $userTotal ?></span>
        <span class="stat-label">Total pengguna</span>
        <span class="muted-note"><?= (int) ($userCounts['user'] ?? 0) ?> pengguna, <?= (int) ($userCounts['provider'] ?? 0) ?> penyelenggara, <?= (int) ($userCounts['admin'] ?? 0) ?> admin</span>
    </a>
    <a class="stat-card" href="<?= e(url('/admin/kegiatan')) ?>">
        <span class="stat-value"><?= (int) $eventTotal ?></span>
        <span class="stat-label">Total kegiatan</span>
        <span class="muted-note"><?= (int) ($eventStatuses['approved'] ?? 0) ?> tayang, <?= (int) ($eventStatuses['pending'] ?? 0) ?> menunggu</span>
    </a>
    <a class="stat-card" href="<?= e(url('/admin/penyedia')) ?>">
        <span class="stat-value"><?= (int) $providerTotal ?></span>
        <span class="stat-label">Penyelenggara</span>
        <span class="muted-note"><?= (int) ($providerStatuses['verified'] ?? 0) ?> terverifikasi, <?= (int) ($providerStatuses['pending'] ?? 0) ?> menunggu</span>
    </a>
    <a class="stat-card" href="<?= e(url('/admin/laporan')) ?>">
        <span class="stat-value"><?= (int) $openReports ?></span>
        <span class="stat-label">Laporan terbuka</span>
        <span class="muted-note">Perlu ditinjau admin</span>
    </a>
    <a class="stat-card" href="<?= e(url('/admin/audit')) ?>">
        <span class="stat-value"><?= (int) $auditTotal ?></span>
        <span class="stat-label">Catatan audit</span>
        <span class="muted-note">Aksi admin tercatat</span>
    </a>
</div>

<section class="section">
    <div class="section-head">
        <h2>Status kegiatan</h2>
        <a href="<?= e(url('/admin/kegiatan')) ?>">Kelola kegiatan</a>
    </div>
    <div class="admin-status-list">
        <?php foreach ($eventLabels as $key => $label): ?>
            <span class="status-badge <?= (int) ($eventStatuses[$key] ?? 0) > 0 ? 'status-open' : 'status-unknown' ?>">
                <?= e($label) ?>: <?= (int) ($eventStatuses[$key] ?? 0) ?>
            </span>
        <?php endforeach; ?>
    </div>
</section>

<section class="section">
    <div class="section-head">
        <h2>Aktivitas terbaru</h2>
        <a href="<?= e(url('/admin/audit')) ?>">Lihat semua</a>
    </div>
    <?php if ($recentAudit === []): ?>
        <p class="empty-state">Belum ada aktivitas admin.</p>
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
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentAudit as $log): ?>
                        <tr>
                            <td><?= e((new DateTimeImmutable((string) $log['created_at']))->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('d M Y H:i')) ?></td>
                            <td><?= e($log['actor_role'] ?? '-') ?> #<?= (int) ($log['actor_user_id'] ?? 0) ?></td>
                            <td><code><?= e($log['action']) ?></code></td>
                            <td><?= e($log['target_type'] ?? '-') ?> #<?= (int) ($log['target_id'] ?? 0) ?></td>
                            <td><span class="admin-meta"><?= e(mb_substr((string) ($log['metadata'] ?? ''), 0, 90)) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
