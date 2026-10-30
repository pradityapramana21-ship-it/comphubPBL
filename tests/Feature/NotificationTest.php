<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Repositories\Pdo\PdoUserRepository;

test('notification badge counts newly published events and mark-read clears it', function (): void {
    signOut();
    $_COOKIE = [];
    $user = (new PdoUserRepository(db()))->create(
        'Pembaca Notifikasi',
        'notif-' . uniqid() . '@example.test',
        password_hash('secret123', PASSWORD_DEFAULT),
        UserRole::User,
    );
    db()->execute('UPDATE users SET events_seen_at = :seen WHERE id = :id', [
        'seen' => (new DateTimeImmutable('-10 minutes'))->format('Y-m-d H:i:sP'),
        'id' => $user['id'],
    ]);
    signIn((int) $user['id']);

    $event = guestEvent('notif', [
        'title' => 'Kegiatan Notifikasi ' . uniqid(),
        'published_at' => (new DateTimeImmutable('-1 minute'))->format('Y-m-d H:i:sP'),
    ]);

    $dashboard = request('GET', '/dasbor')->body();
    assertContains('nav-badge', $dashboard);

    $notifications = request('GET', '/notifikasi')->body();
    assertContains((string) $event['title'], $notifications);

    $read = request('POST', '/notifikasi/tandai-dibaca', []);
    assertSame(302, $read->status());

    assertNotContains((string) $event['title'], request('GET', '/notifikasi')->body());
    assertNotContains('nav-badge', request('GET', '/dasbor')->body());
});
