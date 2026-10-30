<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Repositories\Pdo\PdoProviderProfileRepository;
use App\Repositories\Pdo\PdoUserRepository;
use App\Support\SlugGenerator;

function directoryProvider(string $suffix, string $status, string $email = ''): array
{
    $users = new PdoUserRepository(db());
    $profiles = new PdoProviderProfileRepository(db());
    $user = $users->create(
        'Akun ' . $suffix . ' ' . uniqid(),
        $email !== '' ? $email : 'directory-' . $suffix . '-' . uniqid() . '@example.test',
        password_hash('secret123', PASSWORD_DEFAULT),
        UserRole::Provider,
    );
    $profile = $profiles->create([
        'user_id' => $user['id'],
        'organization_name' => 'Organisasi Direktori ' . $suffix . ' ' . uniqid(),
        'campus_name' => 'Kampus Direktori ' . $suffix,
        'description' => 'Deskripsi organisasi ' . $suffix . '.',
        'website_url' => 'https://organisasi.example.test/' . $suffix,
        'official_email' => 'official-' . $suffix . '-' . uniqid() . '@example.test',
    ]);
    $profiles->update((int) $profile['id'], ['verification_status' => $status]);

    return $profiles->findById((int) $profile['id']);
}

test('provider directory excludes unverified and curation profiles and searches verified ones', function (): void {
    signOut();
    $verified = directoryProvider('terverifikasi', 'verified');
    $pending = directoryProvider('tertunda', 'pending');
    $curation = directoryProvider('kurasi', 'verified', 'kurasi-' . uniqid() . '@internal.invalid');

    $index = request('GET', '/penyelenggara')->body();
    assertSame(200, 200);
    assertContains((string) $verified['organization_name'], $index);
    assertNotContains((string) $pending['organization_name'], $index);
    assertNotContains((string) $curation['organization_name'], $index);

    $search = request('GET', '/penyelenggara?q=' . rawurlencode((string) $verified['campus_name']))->body();
    assertContains((string) $verified['organization_name'], $search);
    assertNotContains((string) $pending['organization_name'], $search);
    assertNotContains((string) $curation['organization_name'], $search);
});

test('verified provider page is public and lists approved events under the guest gate', function (): void {
    signOut();
    $_COOKIE = [];
    $profile = directoryProvider('halaman', 'verified');
    $event = guestEvent('direktori', ['provider_profile_id' => $profile['id']]);
    $slug = (new SlugGenerator())->base((string) $profile['organization_name']);

    $response = request('GET', '/penyelenggara/' . $slug);
    $html = $response->body();

    assertSame(200, $response->status());
    assertContains((string) $profile['organization_name'], $html);
    assertContains('https://organisasi.example.test/halaman', $html);
    assertContains((string) $event['title'], $html);
    assertNotContains((string) $event['registration_url'], $html);
    assertNotContains((string) $profile['official_email'], $html);
});

test('static policy pages render for guests and unknown provider slug returns 404', function (): void {
    signOut();
    foreach (['/tentang', '/kebijakan-moderasi', '/kebijakan-privasi', '/panduan-penyelenggara', '/kontak'] as $path) {
        $response = request('GET', $path);
        assertSame(200, $response->status(), $path);
    }

    $moderation = request('GET', '/kebijakan-moderasi')->body();
    assertContains('Kegiatan ditayangkan setelah diperiksa manual', $moderation);
    assertContains('Halaman resmi penyelenggara', $moderation);

    try {
        request('GET', '/penyelenggara/tidak-ada-' . uniqid());
        throw new RuntimeException('Expected unknown provider to fail');
    } catch (\App\Core\HttpException $exception) {
        $missing = app()->renderError($exception->statusCode, $exception->getMessage());
    }

    assertSame(404, $missing->status());
});
