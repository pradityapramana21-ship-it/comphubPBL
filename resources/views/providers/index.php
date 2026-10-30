<?php /** @var App\Core\Paginator $providers */ /** @var string $keyword */ $paginator = $providers; ?>
<h1>Penyelenggara</h1>
<p>Organisasi yang sudah terverifikasi di CompHub.</p>

<form class="filter-panel" method="get" action="<?= e(url('/penyelenggara')) ?>" role="search">
    <label for="q">Cari organisasi atau kampus</label>
    <input id="q" name="q" type="search" value="<?= e($keyword) ?>">
    <button type="submit" class="button">Cari</button>
    <?php if ($keyword !== ''): ?>
        <a href="<?= e(url('/penyelenggara')) ?>">Reset</a>
    <?php endif; ?>
</form>

<p class="result-count"><?= $providers->total ?> penyelenggara ditemukan.</p>

<?php if ($providers->items === []): ?>
    <p class="empty-state">Belum ada penyelenggara terverifikasi. Kegiatan saat ini dikurasi dari sumber resmi.</p>
<?php else: ?>
    <div class="provider-grid">
        <?php foreach ($providers->items as $profile): ?>
            <?php $slug = (new App\Support\SlugGenerator())->base((string) $profile['organization_name']); ?>
            <article class="provider-card">
                <h2><a href="<?= e(url('/penyelenggara/' . $slug)) ?>"><?= e($profile['organization_name']) ?></a></h2>
                <p class="event-meta"><?= e($profile['campus_name']) ?></p>
                <?php if (! empty($profile['description'])): ?>
                    <p><?= e(mb_substr((string) $profile['description'], 0, 180)) ?></p>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
    <?php require dirname(__DIR__) . '/partials/pagination.php'; ?>
<?php endif; ?>
