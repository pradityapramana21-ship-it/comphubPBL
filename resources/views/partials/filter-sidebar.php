<?php
/** @var App\DataTransferObjects\CatalogFilter $filter */
/** @var list<array<string, mixed>> $categories */
/** @var App\Enums\EventType|null $eventType */
$path = $eventType?->path() ?? '/kegiatan';
$baseQuery = $filter->query();
unset($baseQuery['category'], $baseQuery['page']);
?>
<aside class="filter-sidebar" aria-label="Filter kegiatan">
    <div class="filter-heading">
        <h2>Filter</h2>
        <a href="<?= e(url($path)) ?>">Reset</a>
    </div>

    <section class="filter-group">
        <h3>Topik</h3>
        <ul class="filter-list">
            <li>
                <a href="<?= e(url($path . ($baseQuery === [] ? '' : '?' . http_build_query($baseQuery)))) ?>"
                   class="<?= $filter->category === '' ? 'is-active' : '' ?>">Semua topik</a>
            </li>
            <?php foreach ($categories as $category): ?>
                <?php $categoryQuery = array_merge($baseQuery, ['category' => (string) $category['slug']]); ?>
                <li>
                    <a href="<?= e(url($path . '?' . http_build_query($categoryQuery))) ?>"
                       class="<?= $filter->category === $category['slug'] ? 'is-active' : '' ?>">
                        <?= e($category['name']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <form class="filter-form" method="get" action="<?= e(url($path)) ?>">
        <?php if ($filter->type !== ''): ?>
            <input type="hidden" name="type" value="<?= e($filter->type) ?>">
        <?php endif; ?>
        <label for="filter-mode">Mode</label>
        <select id="filter-mode" name="mode">
            <option value="">Semua mode</option>
            <?php foreach (['online' => 'Online', 'offline' => 'Offline', 'hybrid' => 'Hybrid'] as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $filter->mode === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="filter-status">Pendaftaran</label>
        <select id="filter-status" name="status">
            <option value="open" <?= $filter->status === 'open' ? 'selected' : '' ?>>Dibuka</option>
            <option value="upcoming" <?= $filter->status === 'upcoming' ? 'selected' : '' ?>>Akan dibuka</option>
            <option value="closed" <?= $filter->status === 'closed' ? 'selected' : '' ?>>Ditutup</option>
        </select>

        <label for="filter-sort">Urutkan</label>
        <select id="filter-sort" name="sort">
            <?php foreach (['newest' => 'Terbaru', 'deadline' => 'Tenggat terdekat', 'popular' => 'Paling disimpan'] as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $filter->sort === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>

        <?php if ($filter->q !== ''): ?>
            <input type="hidden" name="q" value="<?= e($filter->q) ?>">
        <?php endif; ?>
        <?php if ($filter->month !== ''): ?>
            <input type="hidden" name="month" value="<?= e($filter->month) ?>">
        <?php endif; ?>

        <button type="submit" class="button">Terapkan</button>
    </form>
</aside>
