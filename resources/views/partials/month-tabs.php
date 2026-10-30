<?php
/** @var App\DataTransferObjects\CatalogFilter $filter */
/** @var list<array{value: string, label: string}> $months */
/** @var App\Enums\EventType|null $eventType */
$path = $eventType?->path() ?? '/kegiatan';
$baseQuery = $filter->query();
unset($baseQuery['month'], $baseQuery['page']);
$allUrl = url($path . ($baseQuery === [] ? '' : '?' . http_build_query($baseQuery)));
?>
<nav class="month-tabs" aria-label="Filter bulan">
    <a href="<?= e($allUrl) ?>" class="<?= $filter->month === '' ? 'is-active' : '' ?>">Semua bulan</a>
    <?php foreach ($months as $month): ?>
        <?php $monthQuery = array_merge($baseQuery, ['month' => $month['value']]); ?>
        <a href="<?= e(url($path . '?' . http_build_query($monthQuery))) ?>"
           class="<?= $filter->month === $month['value'] ? 'is-active' : '' ?>">
            <?= e($month['label']) ?>
        </a>
    <?php endforeach; ?>
</nav>
