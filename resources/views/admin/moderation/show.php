<?php
/** @var array<string, mixed> $event */
/** @var list<array<string, mixed>> $logs */
/** @var string $revisionToken */
/** @var int $revisionTokenEventId */
$statusLabels = [
    'draft' => 'Draf',
    'pending' => 'Menunggu',
    'changes_requested' => 'Perlu revisi',
    'approved' => 'Tayang',
    'rejected' => 'Ditolak',
    'expired' => 'Kedaluwarsa',
    'archived' => 'Diarsipkan',
];
$actionLabels = [
    'submit' => 'Diajukan',
    'approve' => 'Disetujui',
    'reject' => 'Ditolak',
    'request_changes' => 'Minta revisi',
    'archive' => 'Diarsipkan',
    'expire' => 'Kedaluwarsa',
    'unarchive' => 'Dibuka kembali',
    'auto_flagged' => 'Ditandai otomatis',
    'auto_rejected' => 'Ditolak otomatis',
];
$tokenLink = $revisionToken !== '' && $revisionTokenEventId === (int) $event['id']
    ? url('/pengajuan/' . $revisionToken)
    : '';
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Moderasi</p>
        <h1><?= e($event['title']) ?></h1>
        <p class="muted-note">Status saat ini: <strong><?= e($statusLabels[(string) $event['status']] ?? $event['status']) ?></strong></p>
    </div>
    <a class="button button-secondary" href="<?= e(url('/admin/moderasi')) ?>">Kembali ke antrean</a>
</div>

<?php require __DIR__ . '/../_nav.php'; ?>
<?php require dirname(__DIR__, 2) . '/partials/form-errors.php'; ?>

<?php if ($tokenLink !== ''): ?>
    <div class="notice">
        <strong>Tautan revisi baru.</strong>
        <p>Salin sekarang; tautan tidak ditampilkan lagi setelah halaman ditinggalkan.</p>
        <input type="text" readonly value="<?= e($tokenLink) ?>" aria-label="Tautan revisi" onclick="this.select()">
    </div>
<?php endif; ?>

<div class="moderation-grid">
    <section class="section">
        <h2>Data pengajuan</h2>
        <dl class="detail-facts">
            <div><dt>Penyelenggara</dt><dd><?= e(($event['organizer_name'] ?? '') ?: $event['organization_name']) ?></dd></div>
            <div><dt>Email</dt><dd><?= e($event['organizer_email'] ?? '—') ?></dd></div>
            <div><dt>Kontak</dt><dd><?= e($event['contact_number'] ?? '—') ?></dd></div>
            <div><dt>Jenis</dt><dd><?= e($event['event_type'] ?? '—') ?></dd></div>
            <div><dt>Topik</dt><dd><?= e($event['category_name']) ?></dd></div>
            <div><dt>Mode</dt><dd><?= e(ucfirst((string) $event['mode'])) ?></dd></div>
            <div><dt>Lokasi</dt><dd><?= e($event['location_text'] ?? '—') ?></dd></div>
            <div><dt>Jadwal</dt><dd><?= e((new DateTimeImmutable((string) $event['event_start_at']))->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('d M Y H:i')) ?> – <?= e((new DateTimeImmutable((string) $event['event_end_at']))->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('d M Y H:i')) ?></dd></div>
            <div><dt>Tenggat</dt><dd><?= e((new DateTimeImmutable((string) $event['registration_deadline']))->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('d M Y H:i')) ?></dd></div>
            <div><dt>Sumber</dt><dd><?= e($event['source_url'] ?? '—') ?></dd></div>
        </dl>
        <p><?= nl2br(e($event['description'])) ?></p>
        <?php if (trim((string) ($event['requirements'] ?? '')) !== ''): ?>
            <h3>Syarat</h3>
            <p><?= nl2br(e($event['requirements'])) ?></p>
        <?php endif; ?>
        <p class="admin-actions">
            <?php if (trim((string) ($event['poster_path'] ?? '')) !== ''): ?>
                <a class="button button-small button-secondary" href="<?= e(url('/media/poster/' . $event['id'])) ?>" target="_blank" rel="noopener">Lihat poster</a>
            <?php else: ?>
                <span class="muted-note">Poster tidak tersedia.</span>
            <?php endif; ?>
            <?php if (trim((string) ($event['guidebook_path'] ?? '')) !== ''): ?>
                <a class="button button-small button-secondary" href="<?= e(url('/media/guidebook/' . $event['id'])) ?>" target="_blank" rel="noopener">Lihat panduan</a>
            <?php endif; ?>
            <a class="button button-small button-secondary" href="<?= e(url('/admin/kegiatan/' . $event['id'])) ?>">Ubah data</a>
        </p>
    </section>

    <aside class="section">
        <h2>Keputusan</h2>
        <?php if (($event['status'] ?? '') === 'pending'): ?>
            <form method="post" action="<?= e(url('/admin/moderasi/' . $event['id'] . '/setujui')) ?>" class="admin-form">
                <?= csrf_field() ?>
                <button type="submit" class="button">Setujui dan tayangkan</button>
            </form>
        <?php endif; ?>

        <?php if (in_array($event['status'] ?? '', ['pending', 'changes_requested'], true)): ?>
            <form method="post" action="<?= e(url('/admin/moderasi/' . $event['id'] . '/tolak')) ?>" class="admin-form">
                <?= csrf_field() ?>
                <label for="reject-reason">Alasan penolakan</label>
                <textarea id="reject-reason" name="reason" required></textarea>
                <button type="submit" class="button button-danger">Tolak pengajuan</button>
            </form>
        <?php endif; ?>

        <?php if (($event['status'] ?? '') === 'pending'): ?>
            <form method="post" action="<?= e(url('/admin/moderasi/' . $event['id'] . '/minta-revisi')) ?>" class="admin-form">
                <?= csrf_field() ?>
                <label for="revision-reason">Catatan perbaikan</label>
                <textarea id="revision-reason" name="reason" required></textarea>
                <button type="submit" class="button button-secondary">Minta revisi</button>
            </form>
        <?php endif; ?>

        <?php if (($event['status'] ?? '') === 'changes_requested'): ?>
            <form method="post" action="<?= e(url('/admin/moderasi/' . $event['id'] . '/token')) ?>" class="admin-form">
                <?= csrf_field() ?>
                <button type="submit" class="button button-secondary">Terbitkan ulang tautan revisi</button>
            </form>
        <?php endif; ?>

        <?php if (($event['status'] ?? '') !== 'archived'): ?>
            <form method="post" action="<?= e(url('/admin/moderasi/' . $event['id'] . '/arsipkan')) ?>" class="admin-form" onsubmit="return confirm('Arsipkan kegiatan ini? Data dan simpanan tetap utuh.')">
                <?= csrf_field() ?>
                <button type="submit" class="button button-danger">Arsipkan</button>
            </form>
        <?php endif; ?>
    </aside>
</div>

<section class="section">
    <h2>Riwayat moderasi</h2>
    <?php if ($logs === []): ?>
        <p class="empty-state">Belum ada riwayat.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Waktu</th><th>Aksi</th><th>Status</th><th>Alasan</th></tr></thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= e((new DateTimeImmutable((string) $log['created_at']))->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('d M Y H:i')) ?></td>
                            <td><?= e($actionLabels[(string) $log['action']] ?? $log['action']) ?></td>
                            <td><?= e(($log['previous_status'] ?? '—') . ' → ' . ($log['new_status'] ?? '—')) ?></td>
                            <td><?= e($log['reason'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
