<?php
/** @var App\DataTransferObjects\CatalogFilter $filter */
/** @var list<array<string, mixed>> $categories */
/** @var App\Core\Paginator $events */
$paginator = $events;
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Katalog</p>
        <h1>Kegiatan</h1>
        <p class="muted-note">Ringkasan kegiatan terbuka. Masuk untuk membuka detail, tautan pendaftaran, dan sumber resmi.</p>
    </div>
    <?php if (auth_user() === null): ?>
        <a class="button" href="<?= e(url('/masuk?next=' . rawurlencode('/kegiatan'))) ?>">Masuk</a>
    <?php endif; ?>
</div>

<form class="filter-panel" method="get" action="<?= e(url('/kegiatan')) ?>">
    <label for="q">Kata kunci</label>
    <input id="q" name="q" type="search" value="<?= e($filter->q) ?>">

    <label for="category">Kategori</label>
    <select id="category" name="category">
        <option value="">Semua kategori</option>
        <?php foreach ($categories as $category): ?>
            <option value="<?= e($category['slug']) ?>" <?= $filter->category === $category['slug'] ? 'selected' : '' ?>>
                <?= e($category['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label for="mode">Mode</label>
    <select id="mode" name="mode">
        <option value="">Semua mode</option>
        <?php foreach (['online' => 'Online', 'offline' => 'Offline', 'hybrid' => 'Hybrid'] as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $filter->mode === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>

    <label for="status">Status</label>
    <select id="status" name="status">
        <option value="open" <?= $filter->status === 'open' ? 'selected' : '' ?>>Masih buka</option>
        <option value="closed" <?= $filter->status === 'closed' ? 'selected' : '' ?>>Sudah tutup</option>
    </select>

    <label for="sort">Urutkan</label>
    <select id="sort" name="sort">
        <?php foreach (['newest' => 'Terbaru', 'deadline' => 'Tenggat terdekat', 'popular' => 'Paling disimpan'] as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $filter->sort === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>

    <button type="submit" class="button">Terapkan</button>
    <?php if (! $filter->isDefault()): ?>
        <a href="<?= e(url('/kegiatan')) ?>">Reset filter</a>
    <?php endif; ?>
</form>

<p class="result-count"><?= $events->total ?> kegiatan ditemukan.</p>

<?php if ($events->items === []): ?>
    <p class="empty-state">Tidak ada kegiatan yang cocok dengan filter ini.</p>
<?php else: ?>
    <div class="event-grid">
        <?php foreach ($events->items as $event): ?>
            <?php require dirname(__DIR__) . '/partials/event-card.php'; ?>
        <?php endforeach; ?>
    </div>
    <?php require dirname(__DIR__) . '/partials/pagination.php'; ?>
<?php endif; ?>
