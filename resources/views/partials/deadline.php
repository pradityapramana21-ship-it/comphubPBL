<?php
/** @var array<string, mixed> $event */
/** @var App\Support\DateHelper|null $dates */
use App\Support\DateHelper;

$dates ??= new DateHelper();
$deadline = new DateTimeImmutable((string) $event['registration_deadline']);
$confirmed = ! array_key_exists('deadline_confirmed', $event) || (bool) $event['deadline_confirmed'];
?>
<?php if ($confirmed): ?>
    <span class="deadline-label">Tenggat</span> <?= e($dates->date($deadline)) ?> &middot; <?= e($dates->relative($deadline)) ?>
<?php else: ?>
    <span class="deadline-label">Perkiraan tenggat</span> <?= e($dates->date($deadline)) ?> &middot; belum dipastikan
<?php endif; ?>
