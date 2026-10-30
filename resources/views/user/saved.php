<?php
/** @var App\Core\Paginator $events */
$paginator = $events;
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Tersimpan</p>
        <h1>Kegiatan tersimpan</h1>
        <p class="muted-note">Kegiatan yang Anda simpan. Tenggat terdekat tetap tampil di kartu.</p>
    </div>
    <a class="button button-secondary" href="<?= e(url('/kegiatan')) ?>">Cari kegiatan</a>
</div>

<?php if ($events->items === []): ?>
    <p class="empty-state">Belum ada kegiatan tersimpan. Buka katalog lalu tekan Simpan.</p>
<?php else: ?>
    <div class="event-grid">
        <?php foreach ($events->items as $event): ?>
            <?php $isSaved = true; ?>
            <?php require dirname(__DIR__) . '/partials/event-card.php'; ?>
        <?php endforeach; ?>
    </div>
    <?php require dirname(__DIR__) . '/partials/pagination.php'; ?>
<?php endif; ?>
