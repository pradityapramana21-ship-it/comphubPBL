<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Repositories\Pdo\PdoUserRepository;

test('saved page requires auth and lists bookmarked events', function (): void {
    signOut();
    $_COOKIE = [];

    assertSame(302, request('GET', '/tersimpan')->status());

    $event = guestEvent('simpan-' . uniqid());
    $user = (new PdoUserRepository(db()))->create(
        'Pembaca Simpan',
        'simpan-' . uniqid() . '@example.test',
        password_hash('secret123', PASSWORD_DEFAULT),
        UserRole::User,
    );
    signIn((int) $user['id']);

    $saved = request('POST', '/kegiatan/' . $event['id'] . '/bookmark', []);
    assertSame(302, $saved->status());

    $html = request('GET', '/tersimpan')->body();
    assertContains((string) $event['title'], $html);
    assertContains('name="_method" value="DELETE"', $html);

    $removed = request('POST', '/kegiatan/' . $event['id'] . '/bookmark', ['_method' => 'DELETE']);
    assertSame(302, $removed->status());
    assertNotContains((string) $event['title'], request('GET', '/tersimpan')->body());
});
