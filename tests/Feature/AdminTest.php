<?php

declare(strict_types=1);

use App\Core\HttpException;
use App\Enums\ReportReason;
use App\Enums\UserRole;
use App\Repositories\Pdo\PdoCategoryRepository;
use App\Repositories\Pdo\PdoEventRepository;
use App\Repositories\Pdo\PdoProviderProfileRepository;
use App\Repositories\Pdo\PdoReportRepository;
use App\Repositories\Pdo\PdoUserRepository;

function adminUser(string $suffix): array
{
    return (new PdoUserRepository(db()))->create(
        'Admin ' . $suffix . ' ' . uniqid(),
        'admin-' . $suffix . '-' . uniqid() . '@example.test',
        password_hash('secret123', PASSWORD_DEFAULT),
        UserRole::Admin,
    );
}

function adminProvider(string $suffix): array
{
    $users = new PdoUserRepository(db());
    $profiles = new PdoProviderProfileRepository(db());
    $user = $users->create(
        'Penyedia ' . $suffix . ' ' . uniqid(),
        'admin-provider-' . $suffix . '-' . uniqid() . '@example.test',
        password_hash('secret123', PASSWORD_DEFAULT),
        UserRole::Provider,
    );
    $profile = $profiles->create([
        'user_id' => $user['id'],
        'organization_name' => 'Organisasi Admin ' . $suffix . ' ' . uniqid(),
        'campus_name' => 'Kampus Admin ' . $suffix,
        'official_email' => 'admin-org-' . $suffix . '-' . uniqid() . '@example.test',
    ]);

    return $profiles->findById((int) $profile['id']);
}

test('admin area rejects guests and non-admin users', function (): void {
    signOut();
    $_COOKIE = [];

    assertSame(302, request('GET', '/admin')->status());

    $user = (new PdoUserRepository(db()))->create(
        'Pengguna Biasa ' . uniqid(),
        'biasa-' . uniqid() . '@example.test',
        password_hash('secret123', PASSWORD_DEFAULT),
        UserRole::User,
    );
    signIn((int) $user['id']);

    $exception = assertThrows(HttpException::class, static fn () => request('GET', '/admin'));
    assertSame(403, $exception->statusCode);
});

test('admin can create update and delete events with audit logs', function (): void {
    $admin = adminUser('kegiatan');
    $provider = adminProvider('kegiatan');
    $category = (new PdoCategoryRepository(db()))->findBySlug('lomba');
    signIn((int) $admin['id']);

    assertSame(200, request('GET', '/admin')->status());
    assertContains('Monitoring CompHub', request('GET', '/admin')->body());
    assertSame(200, request('GET', '/admin/kegiatan/baru')->status());

    $title = 'Kegiatan Admin ' . uniqid();
    $input = [
        'title' => $title,
        'slug' => '',
        'provider_profile_id' => (string) $provider['id'],
        'category_id' => (string) $category['id'],
        'organizer_name' => 'Organisasi Admin',
        'status' => 'approved',
        'description' => 'Deskripsi kegiatan admin yang lengkap dan jelas.',
        'requirements' => 'Mahasiswa aktif.',
        'registration_url' => 'https://daftar.example.test/admin/' . uniqid(),
        'source_url' => 'https://sumber.example.test/admin/' . uniqid(),
        'source_checked_at' => (new DateTimeImmutable('now'))->format('Y-m-d'),
        'source_note' => 'Sumber resmi diperiksa admin.',
        'mode' => 'online',
        'platform_url' => 'https://meet.example.test/admin',
        'event_start_at' => (new DateTimeImmutable('+10 days'))->format('Y-m-d\TH:i'),
        'event_end_at' => (new DateTimeImmutable('+10 days +2 hours'))->format('Y-m-d\TH:i'),
        'registration_deadline' => (new DateTimeImmutable('+5 days'))->format('Y-m-d\TH:i'),
        'deadline_confirmed' => '1',
        'rejection_reason' => '',
    ];

    $created = request('POST', '/admin/kegiatan', $input);
    assertSame(302, $created->status());

    $events = new PdoEventRepository(db());
    $event = db()->selectOne('SELECT * FROM events WHERE title = :title', ['title' => $title]);
    assertTrue(is_array($event));
    assertSame('approved', $event['status']);

    $update = $input;
    $update['title'] = $title . ' Diubah';
    $update['status'] = 'archived';
    $update['slug'] = (string) $event['slug'];
    $updated = request('POST', '/admin/kegiatan/' . $event['id'], $update);
    assertSame(302, $updated->status());

    $fresh = $events->findById((int) $event['id']);
    assertSame($title . ' Diubah', $fresh['title']);
    assertSame('archived', $fresh['status']);

    $deleted = request('POST', '/admin/kegiatan/' . $event['id'] . '/hapus');
    assertSame(302, $deleted->status());
    assertSame(null, $events->findById((int) $event['id']));

    $auditCount = (int) db()->selectOne(
        "SELECT count(*) AS total FROM audit_logs WHERE action LIKE 'event.%'",
    )['total'];
    assertTrue($auditCount >= 3, 'Expected create, update, and delete audit records');
});

test('admin can manage user roles provider verification and reports', function (): void {
    $admin = adminUser('kelola');
    signIn((int) $admin['id']);

    $users = new PdoUserRepository(db());
    $providers = new PdoProviderProfileRepository(db());
    $newEmail = 'baru-' . uniqid() . '@example.test';
    $createdUser = request('POST', '/admin/pengguna', [
        'name' => 'Pengguna Baru',
        'email' => $newEmail,
        'role' => 'provider',
        'status' => 'active',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ]);
    assertSame(302, $createdUser->status());

    $newUser = $users->findByEmail($newEmail);
    assertTrue(is_array($newUser));
    assertSame('provider', $newUser['role']);
    assertTrue($providers->findByUserId((int) $newUser['id']) !== null);

    $target = $users->create(
        'Calon Penyelenggara ' . uniqid(),
        'calon-' . uniqid() . '@example.test',
        password_hash('secret123', PASSWORD_DEFAULT),
        UserRole::User,
    );

    $updated = request('POST', '/admin/pengguna/' . $target['id'], [
        'name' => $target['name'],
        'email' => $target['email'],
        'role' => 'provider',
        'status' => 'active',
    ]);
    assertSame(302, $updated->status());

    $freshUser = $users->findById((int) $target['id']);
    assertSame('provider', $freshUser['role']);

    $profile = $providers->findByUserId((int) $target['id']);
    assertTrue(is_array($profile));

    $verified = request('POST', '/admin/penyedia/' . $profile['id'], [
        'organization_name' => $profile['organization_name'],
        'campus_name' => $profile['campus_name'],
        'official_email' => $profile['official_email'],
        'verification_status' => 'verified',
        'verification_note' => 'Data organisasi sesuai.',
    ]);
    assertSame(302, $verified->status());

    $freshProfile = $providers->findById((int) $profile['id']);
    assertSame('verified', $freshProfile['verification_status']);
    assertSame((int) $admin['id'], (int) $freshProfile['verified_by']);

    $events = new PdoEventRepository(db());
    $category = (new PdoCategoryRepository(db()))->findBySlug('lomba');
    $event = $events->create([
        'provider_profile_id' => $profile['id'],
        'category_id' => $category['id'],
        'title' => 'Kegiatan Laporan ' . uniqid(),
        'slug' => 'kegiatan-laporan-' . uniqid(),
        'description' => 'Kegiatan untuk menguji laporan admin.',
        'registration_url' => 'https://daftar.example.test/laporan/' . uniqid(),
        'mode' => 'online',
        'event_start_at' => (new DateTimeImmutable('+10 days'))->format('Y-m-d H:i:sP'),
        'event_end_at' => (new DateTimeImmutable('+10 days +1 hour'))->format('Y-m-d H:i:sP'),
        'registration_deadline' => (new DateTimeImmutable('+5 days'))->format('Y-m-d H:i:sP'),
        'status' => 'approved',
        'published_at' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:sP'),
        'source_url' => 'https://sumber.example.test/laporan',
    ]);

    $reports = new PdoReportRepository(db());
    $report = $reports->create((int) $event['id'], (int) $target['id'], ReportReason::Other, 'Perlu ditinjau.');
    $resolved = request('POST', '/admin/laporan/' . $report['id'], [
        'status' => 'resolved',
        'resolution_note' => 'Sudah diperiksa.',
    ]);
    assertSame(302, $resolved->status());

    $freshReport = $reports->findById((int) $report['id']);
    assertSame('resolved', $freshReport['status']);
    assertSame((int) $admin['id'], (int) $freshReport['resolved_by']);

    $selfDelete = request('POST', '/admin/pengguna/' . $admin['id'] . '/hapus');
    assertSame(302, $selfDelete->status());
    assertTrue($users->findById((int) $admin['id']) !== null);

    $delete = request('POST', '/admin/pengguna/' . $target['id'] . '/hapus');
    assertSame(302, $delete->status());
    assertSame(null, $users->findById((int) $target['id']));
});
