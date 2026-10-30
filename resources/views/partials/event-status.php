<?php
/** @var array<string, mixed> $event */
use App\Enums\RegistrationStatus;

$registration = RegistrationStatus::fromEvent($event);
$confirmed = ! array_key_exists('deadline_confirmed', $event) || (bool) $event['deadline_confirmed'];
$label = $confirmed ? $registration->label() : 'Tenggat belum dipastikan';
$class = $confirmed ? $registration->tone() : 'status-unknown';
?>
<span class="status-badge <?= e($class) ?>"><?= e($label) ?></span>
