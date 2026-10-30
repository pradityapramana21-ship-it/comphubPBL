<?php
/** @var array<string, mixed> $event */
/** @var App\Support\DateHelper|null $dates */
use App\Support\DateHelper;

$dates ??= new DateHelper();
$deadline = new DateTimeImmutable((string) $event['registration_deadline']);
$confirmed = ! array_key_exists('deadline_confirmed', $event) || (bool) $event['deadline_confirmed'];
?>
<?php if ($confirmed): ?>
    Tenggat <?= e($dates->date($deadline)) ?> &middot; <?= e($dates->relative($deadline)) ?>
<?php else: ?>
    Tenggat belum dipastikan &middot; perkiraan <?= e($dates->date($deadline)) ?>
<?php endif; ?>
