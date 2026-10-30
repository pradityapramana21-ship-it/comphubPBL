<?php
/** @var array<string, mixed> $event */
/** @var bool $isBookmarked */
/** @var string $registrationUrl */
/** @var string $sourceUrl */
/** @var string $sourceDomain */
use App\Support\DateHelper;

$dates = new DateHelper();
$start = new DateTimeImmutable((string) $event['event_start_at']);
$end = new DateTimeImmutable((string) $event['event_end_at']);
$organizer = (string) ($event['organizer_name'] ?: $event['organization_name']);
?>
<article class="event-detail">
    <nav class="breadcrumb" aria-label="Remah roti">
        <a href="<?= e(url('/kegiatan')) ?>">Kegiatan</a>
        <span aria-hidden="true">/</span>
        <span><?= e($event['title']) ?></span>
    </nav>

    <div class="detail-head">
        <?php require dirname(__DIR__) . '/partials/event-status.php'; ?>
        <span class="event-category"><?= e($event['category_name']) ?></span>
    </div>
    <h1><?= e($event['title']) ?></h1>
    <p class="event-organizer"><?= e($organizer) ?> &middot; <?= e($event['campus_name']) ?></p>

    <dl class="detail-facts">
        <div><dt>Jadwal</dt><dd><?= e($dates->range($start, $end)) ?></dd></div>
        <div><dt>Tenggat pendaftaran</dt><dd><?php require dirname(__DIR__) . '/partials/deadline.php'; ?></dd></div>
        <div><dt>Mode</dt><dd><?= e(ucfirst((string) $event['mode'])) ?></dd></div>
        <?php if (! empty($event['location_text'])): ?>
            <div><dt>Lokasi</dt><dd><?= e($event['location_text']) ?></dd></div>
        <?php endif; ?>
        <?php if (! empty($event['platform_url'])): ?>
            <div><dt>Platform</dt><dd><a href="<?= e($event['platform_url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($event['platform_url']) ?></a></dd></div>
        <?php endif; ?>
        <div><dt>Kategori</dt><dd><?= e($event['category_name']) ?></dd></div>
    </dl>

    <section class="detail-section">
        <h2>Deskripsi</h2>
        <p><?= nl2br(e($event['description'])) ?></p>
    </section>

    <?php if (! empty($event['requirements'])): ?>
        <section class="detail-section">
            <h2>Syarat</h2>
            <p><?= nl2br(e($event['requirements'])) ?></p>
        </section>
    <?php endif; ?>

    <section class="detail-section source-note">
        <h2>Catatan sumber</h2>
        <?php if ($sourceDomain !== ''): ?>
            <p>Sumber: <strong><?= e($sourceDomain) ?></strong></p>
        <?php endif; ?>
        <?php if (! empty($event['source_checked_at'])): ?>
            <p>Diperiksa <?= e($dates->date(new DateTimeImmutable((string) $event['source_checked_at']))) ?></p>
        <?php endif; ?>
        <?php if (! empty($event['source_note'])): ?>
            <p><?= e($event['source_note']) ?></p>
        <?php endif; ?>
    </section>

    <section class="cta-panel">
        <h2>Lanjutkan pendaftaran</h2>
        <p>Tautan resmi membuka situs di luar CompHub.</p>
        <a class="button" href="<?= e($registrationUrl) ?>" target="_blank" rel="noopener noreferrer">Lanjut ke pendaftaran</a>
        <?php if ($sourceUrl !== ''): ?>
            <a class="button button-secondary" href="<?= e($sourceUrl) ?>" target="_blank" rel="noopener noreferrer">Lihat sumber kegiatan</a>
        <?php endif; ?>
        <?php if ($isBookmarked): ?>
            <p class="saved-note">Kegiatan ini tersimpan.</p>
            <form method="post" action="<?= e(url('/kegiatan/' . $event['id'] . '/bookmark')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="_method" value="DELETE">
                <button type="submit" class="button button-secondary">Hapus dari tersimpan</button>
            </form>
        <?php else: ?>
            <form method="post" action="<?= e(url('/kegiatan/' . $event['id'] . '/bookmark')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="button button-secondary">Simpan kegiatan</button>
            </form>
        <?php endif; ?>
    </section>
</article>
