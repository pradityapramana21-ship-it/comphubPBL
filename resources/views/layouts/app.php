<?php /** @var string $title */ /** @var string $content */ /** @var int $notificationCount */ $notificationCount = (int) ($notificationCount ?? 0); ?>
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
                <a href="<?= e(url('/kegiatan')) ?>">Kegiatan</a>
                <a href="<?= e(url('/penyelenggara')) ?>">Penyelenggara</a>
                <?php if (auth_user() !== null): ?>
                    <a href="<?= e(url('/dasbor')) ?>">Dasbor</a>
                    <a href="<?= e(url('/tersimpan')) ?>">Tersimpan</a>
                    <?php if ((auth_user()['role'] ?? '') === 'admin'): ?>
                        <a href="<?= e(url('/admin')) ?>">Admin</a>
                    <?php endif; ?>
                    <a class="nav-bell" href="<?= e(url('/notifikasi')) ?>" aria-label="Notifikasi<?= $notificationCount > 0 ? ' (' . $notificationCount . ' baru)' : '' ?>">
                        Notifikasi
                        <?php if ($notificationCount > 0): ?>
                            <span class="nav-badge"><?= $notificationCount ?></span>
                        <?php endif; ?>
                    </a>
                    <form method="post" action="<?= e(url('/keluar')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="link-button">Keluar</button>
                    </form>
                <?php else: ?>
                    <a href="<?= e(url('/masuk')) ?>">Masuk</a>
                    <a class="button button-small" href="<?= e(url('/daftar')) ?>">Daftar</a>
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
