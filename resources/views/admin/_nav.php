<?php
$adminPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$adminLinks = [
    '/admin' => 'Ringkasan',
    '/admin/pengguna' => 'Pengguna',
    '/admin/kegiatan' => 'Kegiatan',
    '/admin/penyedia' => 'Penyelenggara',
    '/admin/laporan' => 'Laporan',
    '/admin/audit' => 'Audit',
];
?>
<nav class="admin-tabs" aria-label="Navigasi admin">
    <?php foreach ($adminLinks as $href => $label): ?>
        <?php $active = $href === '/admin' ? $adminPath === '/admin' : str_starts_with($adminPath, $href); ?>
        <a href="<?= e(url($href)) ?>" <?= $active ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>
