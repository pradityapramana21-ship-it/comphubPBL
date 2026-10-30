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

test('admin can create update and archive events with audit logs', function (): void {
    $admin = adminUser('kegiatan');
    $provider = adminProvider('kegiatan');
    $category = (new PdoCategoryRepository(db()))->findBySlug('teknologi');
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

    $archived = request('POST', '/admin/kegiatan/' . $event['id'] . '/hapus');
    assertSame(302, $archived->status());
    $afterArchive = $events->findById((int) $event['id']);
    assertTrue(is_array($afterArchive));
    assertSame('archived', $afterArchive['status']);
    assertTrue($afterArchive['archived_at'] !== null);

    $auditCount = (int) db()->selectOne(
        "SELECT count(*) AS total FROM audit_logs WHERE action LIKE 'event.%'",
    )['total'];
    assertTrue($auditCount >= 2, 'Expected create and update audit records');
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
    $category = (new PdoCategoryRepository(db()))->findBySlug('teknologi');
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


test('admin moderation queues, requests revision, reissues token, and archives safely', function (): void {
    $admin = adminUser('moderasi');
    $provider = adminProvider('moderasi');
    $category = (new PdoCategoryRepository(db()))->findBySlug('teknologi');
    $events = new PdoEventRepository(db());
    $event = $events->create([
        'provider_profile_id' => (int) $provider['id'],
        'category_id' => (int) $category['id'],
        'title' => 'Pengajuan Moderasi ' . uniqid(),
        'slug' => 'pengajuan-moderasi-' . uniqid(),
        'description' => 'Deskripsi pengajuan publik untuk antrean moderasi admin.',
        'registration_url' => 'https://daftar.example.test/moderasi/' . uniqid(),
        'mode' => 'online',
        'event_start_at' => (new DateTimeImmutable('+20 days'))->format('Y-m-d H:i:sP'),
        'event_end_at' => (new DateTimeImmutable('+20 days +2 hours'))->format('Y-m-d H:i:sP'),
        'registration_deadline' => (new DateTimeImmutable('+10 days'))->format('Y-m-d H:i:sP'),
        'status' => 'pending',
        'submitted_at' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:sP'),
        'event_type' => 'competition',
        'organizer_name' => 'Organisasi Pengaju',
        'organizer_email' => 'pengaju-' . uniqid() . '@example.test',
        'contact_number' => '08123456789',
        'source_url' => 'https://sumber.example.test/moderasi',
        'source_checked_at' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:sP'),
    ]);

    signIn((int) $admin['id']);
    $queue = request('GET', '/admin/moderasi');
    assertSame(200, $queue->status());
    assertContains((string) $event['title'], $queue->body());

    $detail = request('GET', '/admin/moderasi/' . $event['id']);
    assertSame(200, $detail->status());
    assertContains('Organisasi Pengaju', $detail->body());
    assertContains((string) $event['organizer_email'], $detail->body());

    $revision = request('POST', '/admin/moderasi/' . $event['id'] . '/minta-revisi', [
        'reason' => 'Lengkapi tautan sumber dan kontak.',
    ]);
    assertSame(302, $revision->status());
    $changed = $events->findById((int) $event['id']);
    assertSame('changes_requested', $changed['status']);
    assertTrue($changed['revision_token_hash'] !== null);
    assertTrue($changed['revision_token_expires_at'] !== null);
    assertTrue($changed['revision_token_revoked_at'] === null);

    $log = db()->selectOne(
        'SELECT * FROM moderation_logs WHERE event_id = :id ORDER BY id DESC LIMIT 1',
        ['id' => (int) $event['id']],
    );
    assertSame('pending', $log['previous_status']);
    assertSame('changes_requested', $log['new_status']);

    $oldHash = $changed['revision_token_hash'];
    $reissue = request('POST', '/admin/moderasi/' . $event['id'] . '/token');
    assertSame(302, $reissue->status());
    $reissued = $events->findById((int) $event['id']);
    assertTrue($reissued['revision_token_hash'] !== $oldHash);

    $tokenPage = request('GET', '/admin/moderasi/' . $event['id']);
    assertContains('Tautan revisi baru', $tokenPage->body());
    assertContains('/pengajuan/', $tokenPage->body());

    $user = (new PdoUserRepository(db()))->create(
        'Penyimpan Moderasi ' . uniqid(),
        'simpan-moderasi-' . uniqid() . '@example.test',
        password_hash('secret123', PASSWORD_DEFAULT),
        UserRole::User,
    );
    $bookmarks = new App\Repositories\Pdo\PdoBookmarkRepository(db());
    $bookmarks->add((int) $user['id'], (int) $event['id']);
    $archive = request('POST', '/admin/moderasi/' . $event['id'] . '/arsipkan');
    assertSame(302, $archive->status());
    $archived = $events->findById((int) $event['id']);
    assertSame('archived', $archived['status']);
    assertTrue($archived['archived_at'] !== null);
    assertTrue($bookmarks->exists((int) $user['id'], (int) $event['id']));
    assertTrue((int) db()->selectOne(
        'SELECT count(*) AS total FROM moderation_logs WHERE event_id = :id',
        ['id' => (int) $event['id']],
    )['total'] >= 2);
});

test('admin can approve and reject pending submissions', function (): void {
    $admin = adminUser('putus');
    $provider = adminProvider('putus');
    $category = (new PdoCategoryRepository(db()))->findBySlug('teknologi');
    $events = new PdoEventRepository(db());

    foreach (['approved' => 'setujui', 'rejected' => 'tolak'] as $expected => $route) {
        $event = $events->create([
            'provider_profile_id' => (int) $provider['id'],
            'category_id' => (int) $category['id'],
            'title' => 'Keputusan ' . $route . ' ' . uniqid(),
            'slug' => 'keputusan-' . $route . '-' . uniqid(),
            'description' => 'Deskripsi keputusan moderasi admin yang lengkap.',
            'registration_url' => 'https://daftar.example.test/keputusan/' . uniqid(),
            'mode' => 'online',
            'event_start_at' => (new DateTimeImmutable('+20 days'))->format('Y-m-d H:i:sP'),
            'event_end_at' => (new DateTimeImmutable('+20 days +1 hour'))->format('Y-m-d H:i:sP'),
            'registration_deadline' => (new DateTimeImmutable('+10 days'))->format('Y-m-d H:i:sP'),
            'status' => 'pending',
            'submitted_at' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:sP'),
        ]);

        signIn((int) $admin['id']);
        $response = request('POST', '/admin/moderasi/' . $event['id'] . '/' . $route, [
            'reason' => 'Sudah diperiksa.',
        ]);
        assertSame(302, $response->status());
        $fresh = $events->findById((int) $event['id']);
        assertSame($expected, $fresh['status']);
    }
});
