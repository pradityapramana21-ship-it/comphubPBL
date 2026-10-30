<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Repositories\Pdo\PdoCategoryRepository;
use App\Repositories\Pdo\PdoEventRepository;
use App\Repositories\Pdo\PdoProviderProfileRepository;
use App\Repositories\Pdo\PdoUserRepository;

function guestEvent(string $suffix, array $overrides = []): array
{
    $users = new PdoUserRepository(db());
    $providers = new PdoProviderProfileRepository(db());
    $events = new PdoEventRepository(db());
    $categories = new PdoCategoryRepository(db());
    $owner = $users->create(
        'Penyedia ' . $suffix,
        'provider-' . $suffix . '-' . uniqid() . '@example.test',
        password_hash('secret123', PASSWORD_DEFAULT),
        UserRole::Provider,
    );
    $profile = $providers->create([
        'user_id' => $owner['id'],
        'organization_name' => 'Organisasi ' . $suffix . ' ' . uniqid(),
        'campus_name' => 'Kampus ' . $suffix,
        'official_email' => 'org-' . $suffix . '-' . uniqid() . '@example.test',
    ]);
    $providers->update((int) $profile['id'], ['verification_status' => 'verified']);

    return $events->create($overrides + [
        'provider_profile_id' => $profile['id'],
        'category_id' => (int) $categories->findBySlug('teknologi')['id'],
        'title' => 'Kegiatan ' . $suffix . ' ' . uniqid(),
        'slug' => 'kegiatan-' . $suffix . '-' . uniqid(),
        'description' => 'Deskripsi lengkap kegiatan ' . $suffix . '.',
        'requirements' => 'Mahasiswa aktif.',
        'registration_url' => 'https://daftar.example.test/' . $suffix . '/' . uniqid(),
        'source_url' => 'https://sumber.example.test/' . $suffix . '/' . uniqid(),
        'source_checked_at' => (new DateTimeImmutable('-1 day'))->format('Y-m-d H:i:sP'),
        'source_note' => 'Sumber resmi penyelenggara.',
        'mode' => 'online',
        'platform_url' => 'https://meet.example.test/room',
        'event_start_at' => (new DateTimeImmutable('+10 days'))->format('Y-m-d H:i:sP'),
        'event_end_at' => (new DateTimeImmutable('+10 days +2 hours'))->format('Y-m-d H:i:sP'),
        'registration_deadline' => (new DateTimeImmutable('+5 days'))->format('Y-m-d H:i:sP'),
        'status' => 'approved',
        'published_at' => (new DateTimeImmutable('-1 day'))->format('Y-m-d H:i:sP'),
    ]);
}

test('guest detail redirects to login and never receives registration or source urls', function (): void {
    signOut();
    $_COOKIE = [];
    $event = guestEvent('tamu');
    $response = request('GET', '/kegiatan/' . $event['slug']);
    $location = (string) $response->header('Location');

    assertSame(302, $response->status());
    assertContains('/masuk?next=%2Fkegiatan%2F' . rawurlencode((string) $event['slug']), $location);
    assertSame('', $response->body());
});

test('authenticated detail exposes external registration links with safe rel', function (): void {
    signOut();
    $_COOKIE = [];
    $event = guestEvent('masuk');
    $user = (new PdoUserRepository(db()))->create(
        'Pembaca',
        'pembaca-' . uniqid() . '@example.test',
        password_hash('secret123', PASSWORD_DEFAULT),
        UserRole::User,
    );
    signIn((int) $user['id']);

    $html = request('GET', '/kegiatan/' . $event['slug'])->body();

    assertContains((string) $event['registration_url'], $html);
    assertContains((string) $event['source_url'], $html);
    assertContains('target="_blank" rel="noopener noreferrer"', $html);
});

test('catalog only lists approved open events by default and supports filters and paging', function (): void {
    signOut();
    $_COOKIE = [];
    $open = guestEvent('buka');
    $expired = guestEvent('tutup', [
        'registration_deadline' => (new DateTimeImmutable('-1 day'))->format('Y-m-d H:i:sP'),
    ]);
    $draft = guestEvent('draf', ['status' => 'draft', 'published_at' => null]);

    $default = request('GET', '/kegiatan')->body();
    assertContains((string) $open['title'], $default);
    assertNotContains((string) $expired['title'], $default);
    assertNotContains((string) $draft['title'], $default);
    assertContains('Masuk untuk detail', $default);
    assertContains('/masuk?next=%2Fkegiatan%2F', $default);
    assertNotContains('/kegiatan/' . $open['slug'], $default);

    $closed = request('GET', '/kegiatan?status=closed')->body();
    assertContains((string) $expired['title'], $closed);
    assertNotContains((string) $open['title'], $closed);

    $search = request('GET', '/kegiatan?q=' . rawurlencode((string) $open['title']))->body();
    assertContains((string) $open['title'], $search);
    assertNotContains((string) $expired['title'], $search);

    for ($index = 0; $index < 12; $index++) {
        guestEvent('halaman-' . $index);
    }

    $second = request('GET', '/kegiatan?page=2')->body();
    assertContains('Halaman 2', $second);
});
