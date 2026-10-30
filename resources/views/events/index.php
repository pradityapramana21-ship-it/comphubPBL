<?php
/** @var App\DataTransferObjects\CatalogFilter $filter */
/** @var App\Enums\EventType|null $eventType */
/** @var list<App\Enums\EventType> $types */
/** @var list<array<string, mixed>> $categories */
/** @var list<array{value: string, label: string}> $months */
/** @var App\Core\Paginator $events */
$paginator = $events;
$path = $eventType?->path() ?? '/kegiatan';
?>
<div class="catalog-head">
    <div>
        <p class="eyebrow">Katalog kegiatan</p>
        <h1><?= e($eventType?->label() ?? 'Semua kegiatan') ?></h1>
        <p class="muted-note">Temukan lomba, webinar, dan seminar kampus. Masuk untuk membuka detail dan tautan pendaftaran.</p>
    </div>
    <a class="button" href="<?= e(url('/ajukan')) ?>">Ajukan Kegiatan</a>
</div>

<div class="type-tabs" aria-label="Jenis kegiatan">
    <a href="<?= e(url('/kegiatan')) ?>" class="<?= $eventType === null ? 'is-active' : '' ?>">Semua</a>
    <?php foreach ($types as $type): ?>
        <a href="<?= e(url($type->path())) ?>" class="<?= $eventType === $type ? 'is-active' : '' ?>"><?= e($type->label()) ?></a>
    <?php endforeach; ?>
</div>

<div class="catalog-layout">
    <?php require dirname(__DIR__) . '/partials/filter-sidebar.php'; ?>

    <section class="catalog-main" aria-label="Hasil kegiatan">
        <?php require dirname(__DIR__) . '/partials/month-tabs.php'; ?>
        <p class="result-count"><?= (int) $events->total ?> kegiatan ditemukan<?= $filter->q !== '' ? ' untuk “' . e($filter->q) . '”' : '' ?>.</p>

        <div class="event-grid">
            <?php if ($events->items === []): ?>
                <div class="empty-state">
                    <strong>Tidak ada kegiatan yang cocok.</strong>
                    <p>Coba ubah topik, bulan, atau status pendaftaran.</p>
                </div>
            <?php else: ?>
                <?php foreach ($events->items as $event): ?>
                    <?php require dirname(__DIR__) . '/partials/event-card.php'; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php require dirname(__DIR__) . '/partials/pagination.php'; ?>
    </section>
</div>
