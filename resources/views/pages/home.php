<?php
/** @var list<array<string, mixed>> $latest */
/** @var list<array<string, mixed>> $deadlineSoon */
/** @var list<array<string, mixed>> $categories */
/** @var int $openCount */
?>
<section class="hero hero-split">
    <div class="hero-copy">
        <p class="eyebrow">Informasi kegiatan kampus</p>
        <h1>Lomba, webinar, dan seminar dalam satu tempat.</h1>
        <p>Lihat tanggal, tenggat, dan penyelenggara langsung di beranda. Masuk untuk membuka detail, menyimpan kegiatan, dan mendapat notifikasi kegiatan baru.</p>
        <form class="search-form" method="get" action="<?= e(url('/kegiatan')) ?>" role="search">
            <label for="q">Cari kegiatan</label>
            <div class="search-row">
                <input id="q" name="q" type="search" placeholder="Nama lomba, penyelenggara, atau kampus">
                <button type="submit" class="button">Cari</button>
            </div>
        </form>
        <?php if (auth_user() === null): ?>
            <p class="hero-actions">
                <a class="button" href="<?= e(url('/masuk')) ?>">Masuk</a>
                <a class="button button-secondary" href="<?= e(url('/daftar')) ?>">Buat akun</a>
            </p>
        <?php else: ?>
            <p class="hero-actions">
                <a class="button" href="<?= e(url('/tersimpan')) ?>">Kegiatan tersimpan</a>
                <a class="button button-secondary" href="<?= e(url('/notifikasi')) ?>">Notifikasi</a>
            </p>
        <?php endif; ?>
    </div>
    <aside class="hero-panel glass" aria-label="Ringkasan CompHub">
        <p class="hero-count"><strong><?= (int) $openCount ?></strong> kegiatan terbuka</p>
        <ul class="hero-facts">
            <li><?= count($categories) ?> kategori: <?= e(implode(', ', array_column($categories, 'name'))) ?></li>
            <li>Tanggal dan tenggat tampil di setiap kartu</li>
            <li>Simpan dan notifikasi tersedia setelah masuk</li>
        </ul>
    </aside>
</section>

<section class="section" aria-labelledby="kategori-heading">
    <div class="section-head">
        <h2 id="kategori-heading">Kategori</h2>
        <a href="<?= e(url('/kegiatan')) ?>">Lihat semua kegiatan</a>
    </div>
    <ul class="category-list">
        <li><a href="<?= e(url('/kegiatan')) ?>">Semua</a></li>
        <?php foreach ($categories as $category): ?>
            <li><a href="<?= e(url('/kegiatan?category=' . rawurlencode((string) $category['slug']))) ?>"><?= e($category['name']) ?></a></li>
        <?php endforeach; ?>
    </ul>
</section>

<section class="section" aria-labelledby="terbaru-heading">
    <div class="section-head">
        <h2 id="terbaru-heading">Kegiatan terbaru</h2>
        <a href="<?= e(url('/kegiatan')) ?>">Lihat semua</a>
    </div>
    <?php if ($latest === []): ?>
        <p class="empty-state">Belum ada kegiatan terbuka.</p>
    <?php else: ?>
        <div class="event-grid">
            <?php foreach ($latest as $event): ?>
                <?php require dirname(__DIR__) . '/partials/event-card.php'; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="section" aria-labelledby="tenggat-heading">
    <div class="section-head">
        <h2 id="tenggat-heading">Segera tutup</h2>
        <a href="<?= e(url('/kegiatan?sort=deadline')) ?>">Urutkan tenggat</a>
    </div>
    <?php if ($deadlineSoon === []): ?>
        <p class="empty-state">Belum ada tenggat dalam 14 hari ke depan.</p>
    <?php else: ?>
        <div class="event-grid">
            <?php foreach ($deadlineSoon as $event): ?>
                <?php require dirname(__DIR__) . '/partials/event-card.php'; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="section steps" aria-labelledby="alur-heading">
    <h2 id="alur-heading">Alur singkat</h2>
    <ol class="step-list">
        <li><strong>Lihat ringkasan</strong><span>Tanggal, tenggat, penyelenggara, dan status tersedia di beranda dan katalog.</span></li>
        <li><strong>Masuk</strong><span>Detail lengkap, tautan pendaftaran resmi, dan sumber kegiatan terbuka setelah masuk.</span></li>
        <li><strong>Simpan</strong><span>Simpan kegiatan dan dapatkan notifikasi saat ada kegiatan baru.</span></li>
    </ol>
</section>

<section class="section provider-cta">
    <h2>Direktori penyelenggara</h2>
    <p>Lihat organisasi yang sudah terverifikasi di CompHub.</p>
    <a class="button button-secondary" href="<?= e(url('/penyelenggara')) ?>">Buka direktori penyelenggara</a>
</section>
