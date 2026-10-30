<?php
/** @var array<string, mixed> $profile */
/** @var list<array<string, mixed>> $events */
$website = (string) ($profile['website_url'] ?? '');
$websiteOk = str_starts_with($website, 'https://');
$domain = App\Support\UrlHelper::domain($website);
?>
<article class="provider-detail">
    <nav class="breadcrumb" aria-label="Remah roti">
        <a href="<?= e(url('/penyelenggara')) ?>">Penyelenggara</a>
        <span aria-hidden="true">/</span>
        <span><?= e($profile['organization_name']) ?></span>
    </nav>
    <h1><?= e($profile['organization_name']) ?></h1>
    <p class="event-organizer"><?= e($profile['campus_name']) ?></p>
    <?php if (! empty($profile['description'])): ?>
        <p><?= nl2br(e($profile['description'])) ?></p>
    <?php endif; ?>
    <?php if ($websiteOk && $domain !== ''): ?>
        <p>Situs resmi: <a href="<?= e($website) ?>" target="_blank" rel="noopener noreferrer"><?= e($domain) ?></a></p>
    <?php endif; ?>

    <h2>Kegiatan yang disetujui</h2>
    <?php if ($events === []): ?>
        <p class="empty-state">Belum ada kegiatan yang disetujui.</p>
    <?php else: ?>
        <div class="event-grid">
            <?php foreach ($events as $event): ?>
                <?php require dirname(__DIR__) . '/partials/event-card.php'; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</article>
