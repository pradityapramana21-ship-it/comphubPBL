<?php
/** @var string $title */
/** @var string $content */
/** @var int $notificationCount */
$notificationCount = (int) ($notificationCount ?? 0);
$currentUser = auth_user();
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$navItems = [
    '/lomba' => 'Lomba',
    '/webinar' => 'Webinar',
    '/seminar' => 'Seminar',
    '/penyelenggara' => 'Penyelenggara',
];
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'CompHub') ?> — CompHub</title>
    <?php if (! empty($description)): ?>
        <meta name="description" content="<?= e($description) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= e(asset('/assets/css/app.css')) ?>">
    <script src="<?= e(asset('/assets/js/app.js')) ?>" defer></script>
</head>
<body>
    <a class="skip-link" href="#main">Lewati ke konten</a>
    <header class="site-header">
        <nav class="site-nav" aria-label="Navigasi utama">
            <a class="brand" href="<?= e(url('/')) ?>">CompHub</a>
            <button type="button" class="nav-toggle" data-nav-toggle aria-expanded="false" aria-controls="primary-nav">Menu</button>
            <div class="nav-links" id="primary-nav" data-nav>
                <?php foreach ($navItems as $href => $label): ?>
                    <a href="<?= e(url($href)) ?>" <?= $currentPath === $href ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
                <?php endforeach; ?>
                <a class="nav-cta" href="<?= e(url('/ajukan')) ?>">Ajukan Kegiatan</a>
                <form class="nav-search" method="get" action="<?= e(url('/kegiatan')) ?>" role="search">
                    <label class="visually-hidden" for="nav-q">Cari kegiatan</label>
                    <input id="nav-q" name="q" type="search" placeholder="Cari kegiatan">
                    <button type="submit" class="button button-small">Cari</button>
                </form>
                <?php if ($currentUser !== null): ?>
                    <details class="profile-menu">
                        <summary><?= e($currentUser['name'] ?? 'Akun') ?></summary>
                        <div class="profile-menu-panel">
                            <a href="<?= e(url('/dasbor')) ?>">Dasbor</a>
                            <a href="<?= e(url('/tersimpan')) ?>">Tersimpan</a>
                            <a href="<?= e(url('/notifikasi')) ?>">Notifikasi<?php if ($notificationCount > 0): ?><span class="nav-badge"><?= $notificationCount ?></span><?php endif; ?></a>
                            <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
                                <a href="<?= e(url('/admin')) ?>">Admin</a>
                            <?php endif; ?>
                            <form method="post" action="<?= e(url('/keluar')) ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="link-button">Keluar</button>
                            </form>
                        </div>
                    </details>
                <?php else: ?>
                    <a href="<?= e(url('/masuk')) ?>">Masuk</a>
                <?php endif; ?>
            </div>
        </nav>
    </header>
    <main id="main" class="site-main">
        <?php $status = flash('status'); ?>
        <?php if (is_string($status) && $status !== ''): ?>
            <div class="alert alert-success" role="status"><?= e($status) ?>
                <button type="button" class="alert-dismiss" data-dismiss aria-label="Tutup">Tutup</button>
            </div>
        <?php endif; ?>
        <?= $content ?>
    </main>
    <footer class="site-footer">
        <p>CompHub — direktori lomba kampus.</p>
    </footer>
</body>
</html>
