<?php
/** @var array<string, mixed> $event */
use App\Support\DateHelper;

$dates ??= new DateHelper();
$start = new DateTimeImmutable((string) $event['event_start_at']);
$end = new DateTimeImmutable((string) $event['event_end_at']);
$organizer = (string) ($event['organizer_name'] ?: $event['organization_name']);
$detailPath = '/kegiatan/' . $event['slug'];
$isUser = auth_user() !== null;
$detailUrl = $isUser ? url($detailPath) : url('/masuk?next=' . rawurlencode($detailPath));
$description = trim((string) preg_replace('/\s+/', ' ', (string) $event['description']));
$excerpt = mb_substr($description, 0, 150) . (mb_strlen($description) > 150 ? '…' : '');
?>
<article class="event-card">
    <div class="event-card-head">
        <?php require __DIR__ . '/event-status.php'; ?>
        <span class="event-category"><?= e($event['category_name']) ?></span>
    </div>
    <h3><a href="<?= e($detailUrl) ?>"><?= e($event['title']) ?></a></h3>
    <p class="event-organizer"><?= e($organizer) ?> &middot; <?= e($event['campus_name']) ?></p>
    <p class="event-meta"><?= e($dates->range($start, $end)) ?></p>
    <p class="event-meta">Mode: <?= e(ucfirst((string) $event['mode'])) ?></p>
    <p class="event-deadline"><?php require __DIR__ . '/deadline.php'; ?></p>
    <p class="event-excerpt"><?= e($excerpt) ?></p>
    <div class="event-card-actions">
        <a class="button button-small" href="<?= e($detailUrl) ?>"><?= $isUser ? 'Lihat detail' : 'Masuk untuk detail' ?></a>
        <?php if ($isUser): ?>
            <form class="bookmark-form" method="post" action="<?= e(url('/kegiatan/' . $event['id'] . '/bookmark')) ?>">
                <?= csrf_field() ?>
                <?php if ($isSaved ?? false): ?>
                    <input type="hidden" name="_method" value="DELETE">
                    <button type="submit" class="button button-secondary button-small">Hapus</button>
                <?php else: ?>
                    <button type="submit" class="button button-secondary button-small">Simpan</button>
                <?php endif; ?>
            </form>
        <?php else: ?>
            <a class="button button-secondary button-small" href="<?= e(url('/masuk?next=' . rawurlencode($detailPath))) ?>">Simpan</a>
        <?php endif; ?>
    </div>
</article>
