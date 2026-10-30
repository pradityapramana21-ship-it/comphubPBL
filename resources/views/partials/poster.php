<?php
/** @var array<string, mixed> $event */
use App\Enums\EventType;

$type = EventType::tryFrom((string) ($event['event_type'] ?? ''));
$label = $type?->label() ?? 'Kegiatan';
$poster = trim((string) ($event['poster_path'] ?? ''));
?>
<div class="event-poster <?= $poster === '' ? 'event-poster-placeholder' : '' ?>">
    <?php if ($poster !== ''): ?>
        <img src="<?= e(url('/media/poster/' . $event['id'])) ?>" alt="Poster <?= e($event['title'] ?? 'kegiatan') ?>" loading="lazy">
    <?php else: ?>
        <span class="poster-type"><?= e($label) ?></span>
        <span class="poster-mark">CompHub</span>
    <?php endif; ?>
</div>
