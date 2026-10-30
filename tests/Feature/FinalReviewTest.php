<?php

declare(strict_types=1);

use App\Core\HttpException;
use App\Core\Request;
use App\Core\ValidationException;
use App\Enums\UserRole;
use App\Http\Requests\Auth\RegisterRequest;
use App\Repositories\Pdo\PdoCategoryRepository;
use App\Repositories\Pdo\PdoEventRepository;
use App\Repositories\Pdo\PdoProviderProfileRepository;
use App\Repositories\Pdo\PdoUserRepository;
use App\Services\CurationProfileService;
use App\Services\EventSubmissionService;
use App\Services\ModerationService;
use App\Services\UploadService;

test('serve launcher raises upload limits above the five megabyte guidebook cap', function (): void {
    $path = dirname(__DIR__, 2) . '/bin/serve';
    assertTrue(is_file($path), 'bin/serve is missing');
    $contents = (string) file_get_contents($path);
    assertContains('upload_max_filesize=8M', $contents);
    assertContains('post_max_size=12M', $contents);
});

test('archived events cannot be moved back into revision', function (): void {
    $provider = adminProvider('arsip-revisi');
    $category = (new PdoCategoryRepository(db()))->findBySlug('teknologi');
    $events = new PdoEventRepository(db());
    $event = $events->create([
        'provider_profile_id' => (int) $provider['id'],
        'category_id' => (int) $category['id'],
        'title' => 'Arsip Revisi ' . uniqid(),
        'slug' => 'arsip-revisi-' . uniqid(),
        'description' => 'Kegiatan arsip yang tidak boleh diminta revisi.',
        'registration_url' => 'https://daftar.example.test/arsip/' . uniqid(),
        'mode' => 'online',
        'event_start_at' => (new DateTimeImmutable('+20 days'))->format('Y-m-d H:i:sP'),
        'event_end_at' => (new DateTimeImmutable('+20 days +1 hour'))->format('Y-m-d H:i:sP'),
        'registration_deadline' => (new DateTimeImmutable('+10 days'))->format('Y-m-d H:i:sP'),
        'status' => 'archived',
        'archived_at' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:sP'),
    ]);

    assertThrows(ValidationException::class, fn () => app()->container()->make(ModerationService::class)
        ->requestChanges((int) $event['id'], (int) adminUser('arsip-revisi')['id'], 'Coba revisi.'));
});

test('reject action is not offered when transition is invalid', function (): void {
    $admin = adminUser('tombol-tolak');
    $provider = adminProvider('tombol-tolak');
    $category = (new PdoCategoryRepository(db()))->findBySlug('teknologi');
    $events = new PdoEventRepository(db());
    $event = $events->create([
        'provider_profile_id' => (int) $provider['id'],
        'category_id' => (int) $category['id'],
        'title' => 'Tombol Tolak ' . uniqid(),
        'slug' => 'tombol-tolak-' . uniqid(),
        'description' => 'Kegiatan untuk memeriksa tombol tolak.',
        'registration_url' => 'https://daftar.example.test/tolak/' . uniqid(),
        'mode' => 'online',
        'event_start_at' => (new DateTimeImmutable('+20 days'))->format('Y-m-d H:i:sP'),
        'event_end_at' => (new DateTimeImmutable('+20 days +1 hour'))->format('Y-m-d H:i:sP'),
        'registration_deadline' => (new DateTimeImmutable('+10 days'))->format('Y-m-d H:i:sP'),
        'status' => 'changes_requested',
    ]);

    signIn((int) $admin['id']);
    $body = request('GET', '/admin/moderasi/' . $event['id'])->body();
    assertNotContains('/tolak', $body);
    assertNotContains('Tolak pengajuan', $body);
});

test('public submission rejects an inactive topic', function (): void {
    curationFixture();
    $inactive = (new PdoCategoryRepository(db()))->findBySlug('lomba');
    assertTrue($inactive !== null);

    $exception = assertThrows(ValidationException::class, fn () => rawRequest(
        'POST',
        '/ajukan',
        submissionInput([
            'title' => 'Topik Nonaktif ' . uniqid(),
            'category_id' => (string) $inactive['id'],
        ]),
        [],
        ['poster' => pngUpload(), 'guidebook' => pdfUpload()],
    ));
    assertTrue(isset($exception->errors()['category_id']));
});

test('form request removes passwords from flashed old input', function (): void {
    $request = Request::create('POST', '/daftar', [
        'name' => 'Uji Sandi',
        'email' => 'sandi-' . uniqid() . '@example.test',
        'password' => 'pendek',
        'password_confirmation' => 'pendek',
    ]);

    $exception = assertThrows(ValidationException::class, fn () => (new RegisterRequest($request))->validate());
    assertFalse(array_key_exists('password', $exception->old()));
});

test('revision preserves the original provider owner', function (): void {
    $provider = adminProvider('pemilik');
    $category = (new PdoCategoryRepository(db()))->findBySlug('teknologi');
    $events = new PdoEventRepository(db());
    $event = $events->create([
        'provider_profile_id' => (int) $provider['id'],
        'category_id' => (int) $category['id'],
        'title' => 'Pemilik Revisi ' . uniqid(),
        'slug' => 'pemilik-revisi-' . uniqid(),
        'description' => 'Kegiatan milik penyedia lain yang tidak boleh berpindah.',
        'registration_url' => 'https://daftar.example.test/pemilik/' . uniqid(),
        'mode' => 'online',
        'event_start_at' => (new DateTimeImmutable('+20 days'))->format('Y-m-d H:i:sP'),
        'event_end_at' => (new DateTimeImmutable('+20 days +1 hour'))->format('Y-m-d H:i:sP'),
        'registration_deadline' => (new DateTimeImmutable('+10 days'))->format('Y-m-d H:i:sP'),
        'status' => 'changes_requested',
    ]);

    $updated = app()->container()->make(EventSubmissionService::class)->revise(
        $event,
        submissionInput([
            'title' => 'Pemilik Revisi Baru ' . uniqid(),
            'organizer_email' => 'owner-' . uniqid() . '@example.test',
        ]),
        null,
        null,
    );

    assertSame((int) $provider['id'], (int) $updated['provider_profile_id']);
});

test('curation profile is recreated for a fresh database', function (): void {
    try {
        db()->transaction(function (): void {
            db()->execute("DELETE FROM users WHERE id IN (
                SELECT user_id FROM provider_profiles WHERE organization_name = 'Tim Kurasi CompHub'
            )");
            db()->execute("DELETE FROM provider_profiles WHERE organization_name = 'Tim Kurasi CompHub'");

            $profile = (new CurationProfileService(db()))->profile();
            assertSame('Tim Kurasi CompHub', $profile['organization_name']);
            assertSame('verified', $profile['verification_status']);

            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException $exception) {
        assertSame('rollback', $exception->getMessage());
    }
});

test('upload service accepts a guidebook near five megabytes', function (): void {
    $content = "%PDF-1.4\n" . str_repeat('A', 4_900_000);
    $file = submissionUpload($content, 'besar.pdf');
    $uploads = new UploadService(dirname(__DIR__, 2) . '/storage/uploads');

    try {
        $path = $uploads->store($file, 'guidebook');
        assertTrue(str_starts_with($path, 'guidebooks/'));
        $uploads->remove($path);
    } finally {
        @unlink($file['tmp_name']);
    }
});
