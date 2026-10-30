<?php /** @var list<array<string, mixed>> $events */ ?>
<div class="page-head">
    <div>
        <p class="eyebrow">Notifikasi</p>
        <h1>Kegiatan baru</h1>
        <p class="muted-note">Kegiatan yang tayang sejak terakhir Anda membuka halaman ini.</p>
    </div>
    <?php if ($events !== []): ?>
        <form method="post" action="<?= e(url('/notifikasi/tandai-dibaca')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="button button-secondary">Tandai semua dibaca</button>
        </form>
    <?php endif; ?>
</div>

<?php if ($events === []): ?>
    <p class="empty-state">Belum ada kegiatan baru. Anda akan melihatnya di sini saat tim kurasi menayangkan kegiatan.</p>
<?php else: ?>
    <div class="event-grid">
        <?php foreach ($events as $event): ?>
            <?php require dirname(__DIR__) . '/partials/event-card.php'; ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
