<?php
/** @var App\Core\Paginator $events */
$paginator = $events;
$labels = [
    'pending' => 'Menunggu',
    'changes_requested' => 'Perlu revisi',
];
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Admin</p>
        <h1>Moderasi</h1>
        <p class="muted-note">Tinjau pengajuan publik, verifikasi penyelenggara, lalu setujui, tolak, atau minta revisi.</p>
    </div>
</div>

<?php require __DIR__ . '/../_nav.php'; ?>

<p class="result-count"><?= (int) $events->total ?> pengajuan dalam antrean.</p>

<?php if ($events->items === []): ?>
    <p class="empty-state">Antrean moderasi kosong.</p>
<?php else: ?>
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Kegiatan</th>
                    <th>Penyelenggara</th>
                    <th>Jenis</th>
                    <th>Status</th>
                    <th>Diajukan</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($events->items as $event): ?>
                    <tr>
                        <td>
                            <a href="<?= e(url('/admin/moderasi/' . $event['id'])) ?>"><?= e($event['title']) ?></a>
                            <div class="admin-meta"><?= e($event['organizer_email'] ?? 'Email belum diisi') ?></div>
                        </td>
                        <td><?= e(($event['organizer_name'] ?? '') ?: $event['organization_name']) ?></td>
                        <td><?= e($event['event_type'] ?? '—') ?></td>
                        <td><span class="status-badge status-soon"><?= e($labels[(string) $event['status']] ?? $event['status']) ?></span></td>
                        <td><?= e((new DateTimeImmutable((string) $event['submitted_at']))->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('d M Y H:i')) ?></td>
                        <td><a class="button button-small button-secondary" href="<?= e(url('/admin/moderasi/' . $event['id'])) ?>">Tinjau</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php require dirname(__DIR__, 2) . '/partials/pagination.php'; ?>
<?php endif; ?>
