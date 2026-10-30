<?php
/** @var array<string, mixed> $event */
$confirmed = ! array_key_exists('deadline_confirmed', $event) || (bool) $event['deadline_confirmed'];
$deadline = new DateTimeImmutable((string) $event['registration_deadline']);
$now = new DateTimeImmutable('now');
$days = (int) $now->diff($deadline)->format('%r%a');

if (! $confirmed) {
    $statusLabel = 'Tenggat belum pasti';
    $statusClass = 'status-unknown';
} else {
    $statusLabel = $deadline < $now ? 'Ditutup' : ($days <= 3 ? 'Segera tutup' : 'Buka');
    $statusClass = $deadline < $now ? 'status-closed' : ($days <= 3 ? 'status-soon' : 'status-open');
}
?>
<span class="status-badge <?= e($statusClass) ?>"><?= e($statusLabel) ?></span>
