<?php
/** @var array<string, mixed> $user */
/** @var int $savedCount */
/** @var int $newCount */
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Dasbor</p>
        <h1>Halo, <?= e($user['name']) ?>.</h1>
        <p class="muted-note">Kelola kegiatan yang Anda simpan dan pantau informasi baru.</p>
    </div>
    <a class="button button-secondary" href="<?= e(url('/profil')) ?>">Profil</a>
</div>

<div class="stat-grid">
    <a class="stat-card glass" href="<?= e(url('/tersimpan')) ?>">
        <span class="stat-value"><?= (int) $savedCount ?></span>
        <span class="stat-label">Kegiatan tersimpan</span>
    </a>
    <a class="stat-card glass" href="<?= e(url('/notifikasi')) ?>">
        <span class="stat-value"><?= (int) $newCount ?></span>
        <span class="stat-label">Notifikasi baru</span>
    </a>
    <div class="stat-card glass">
        <span class="stat-value stat-value-small"><?= e($user['email']) ?></span>
        <span class="stat-label">Email akun</span>
    </div>
</div>

<section class="section" aria-labelledby="aksi-heading">
    <h2 id="aksi-heading">Langkah berikutnya</h2>
    <ul class="link-list">
        <li><a href="<?= e(url('/kegiatan')) ?>">Cari kegiatan baru</a> lalu simpan yang ingin diikuti.</li>
        <li><a href="<?= e(url('/tersimpan')) ?>">Buka kegiatan tersimpan</a> untuk melihat tenggat terdekat.</li>
        <li><a href="<?= e(url('/notifikasi')) ?>">Periksa notifikasi</a> untuk kegiatan yang baru tayang.</li>
    </ul>
</section>
