<?php

declare(strict_types=1);

use App\Core\HttpException;
use App\Core\ValidationException;
use App\Enums\UserRole;
use App\Repositories\Pdo\PdoEventRepository;
use App\Repositories\Pdo\PdoProviderProfileRepository;
use App\Repositories\Pdo\PdoUserRepository;
use App\Services\RevisionTokenService;

/** @return array<string, mixed> */
function curationFixture(): array
{
    $existing = db()->selectOne(
        "SELECT p.* FROM provider_profiles p
         WHERE p.organization_name = 'Tim Kurasi CompHub' AND p.verification_status = 'verified'
         LIMIT 1",
    );

    if ($existing !== null) {
        return $existing;
    }

    $user = (new PdoUserRepository(db()))->create(
        'Tim Kurasi CompHub',
        'kurasi-' . uniqid() . '@internal.invalid',
        password_hash('secret123', PASSWORD_DEFAULT),
        UserRole::Provider,
    );
    $provider = (new PdoProviderProfileRepository(db()))->create([
        'user_id' => (int) $user['id'],
        'organization_name' => 'Tim Kurasi CompHub',
        'campus_name' => 'Lintas kampus',
        'official_email' => 'kurasi@internal.invalid',
        'description' => 'Profil teknis untuk kegiatan yang diajukan publik.',
    ]);

    return (new PdoProviderProfileRepository(db()))->update((int) $provider['id'], [
        'verification_status' => 'verified',
    ]);
}

function submissionUpload(string $content, string $name): array
{
    $path = tempnam(sys_get_temp_dir(), 'comphub-upload-');
    file_put_contents($path, $content);

    return [
        'name' => $name,
        'type' => 'application/octet-stream',
        'tmp_name' => $path,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($path),
    ];
}

function pngUpload(): array
{
    return submissionUpload(
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
        'poster.png',
    );
}

function pdfUpload(): array
{
    return submissionUpload("%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Count 0>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF", 'guidebook.pdf');
}

/** @return array<string, mixed> */
function submissionInput(array $overrides = []): array
{
    $start = new DateTimeImmutable('+21 days');
    $end = $start->modify('+4 hours');
    $deadline = new DateTimeImmutable('+14 days');

    return $overrides + [
        'event_type' => 'competition',
        'title' => 'Lomba Uji Publik ' . uniqid(),
        'organizer_name' => 'Himpunan Uji',
        'organizer_email' => 'organizer-' . uniqid() . '@example.test',
        'contact_number' => '081234567890',
        'category_id' => (string) db()->selectOne("SELECT id FROM categories WHERE slug = 'teknologi'")['id'],
        'description' => 'Deskripsi kegiatan uji yang cukup panjang untuk validasi publik.',
        'requirements' => 'Mahasiswa aktif.',
        'registration_url' => 'https://example.test/daftar',
        'source_url' => 'https://example.test/sumber',
        'mode' => 'offline',
        'location_text' => 'Gedung Uji, Surabaya',
        'platform_url' => '',
        'registration_start_at' => (new DateTimeImmutable('-1 day'))->format('Y-m-d H:i:sP'),
        'registration_deadline' => $deadline->format('Y-m-d H:i:sP'),
        'event_start_at' => $start->format('Y-m-d H:i:sP'),
        'event_end_at' => $end->format('Y-m-d H:i:sP'),
        'website' => '',
    ];
}

test('public submission creates a pending event owned by the curation profile', function (): void {
    $curation = curationFixture();
    $input = submissionInput();

    $response = rawRequest('POST', '/ajukan', $input, [], [
        'poster' => pngUpload(),
        'guidebook' => pdfUpload(),
    ]);

    assertSame(302, $response->status());
    assertContains('/ajukan/berhasil', (string) $response->header('Location'));

    $event = db()->selectOne(
        'SELECT * FROM events WHERE organizer_email = :email ORDER BY id DESC LIMIT 1',
        ['email' => $input['organizer_email']],
    );

    assertTrue($event !== null);
    assertSame('pending', $event['status']);
    assertSame((int) $curation['id'], (int) $event['provider_profile_id']);
    assertSame('competition', $event['event_type']);
    assertSame($input['title'], $event['title']);
    assertTrue(str_starts_with((string) $event['poster_path'], 'posters/'));
    assertTrue(str_starts_with((string) $event['guidebook_path'], 'guidebooks/'));
    assertSame(null, $event['revision_token_hash']);
});

test('public submission requires poster and competition guidebook', function (): void {
    curationFixture();
    $missingPoster = assertThrows(ValidationException::class, fn () => rawRequest(
        'POST',
        '/ajukan',
        submissionInput(['title' => 'Tanpa Poster ' . uniqid()]),
    ));
    assertTrue(isset($missingPoster->errors()['poster']));

    $missingGuidebook = assertThrows(ValidationException::class, fn () => rawRequest(
        'POST',
        '/ajukan',
        submissionInput(['title' => 'Tanpa Panduan ' . uniqid()]),
        [],
        ['poster' => pngUpload()],
    ));
    assertTrue(isset($missingGuidebook->errors()['guidebook']));
});

test('revision token stores only a hash and is single-use after revision', function (): void {
    curationFixture();
    $input = submissionInput(['title' => 'Revisi Uji ' . uniqid()]);
    rawRequest('POST', '/ajukan', $input, [], ['poster' => pngUpload(), 'guidebook' => pdfUpload()]);
    $event = db()->selectOne(
        'SELECT * FROM events WHERE organizer_email = :email ORDER BY id DESC LIMIT 1',
        ['email' => $input['organizer_email']],
    );
    $events = new PdoEventRepository(db());
    $events->update((int) $event['id'], ['status' => 'changes_requested']);
    $event = $events->findById((int) $event['id']);

    $service = new RevisionTokenService($events);
    $token = $service->issue((int) $event['id']);

    $stored = $events->findById((int) $event['id']);
    assertSame(hash('sha256', $token), $stored['revision_token_hash']);
    assertFalse($token === $stored['revision_token_hash']);
    assertTrue($stored['revision_token_expires_at'] !== null);
    assertSame((int) $event['id'], (int) $service->resolve($token)['id']);

    $revisionInput = submissionInput([
        'title' => 'Judul Direvisi ' . uniqid(),
        'organizer_email' => $input['organizer_email'],
    ]);
    $response = rawRequest('POST', '/pengajuan/' . $token, $revisionInput, [], []);
    assertSame(302, $response->status());

    $revised = $events->findById((int) $event['id']);
    assertSame('pending', $revised['status']);
    assertSame($revisionInput['title'], $revised['title']);
    assertTrue($revised['revision_token_revoked_at'] !== null);
    assertSame(null, $service->resolve($token));
});

test('revision token page sends no-store no-referrer and rejects invalid tokens', function (): void {
    curationFixture();
    $input = submissionInput(['title' => 'Halaman Token ' . uniqid()]);
    rawRequest('POST', '/ajukan', $input, [], ['poster' => pngUpload(), 'guidebook' => pdfUpload()]);
    $event = db()->selectOne(
        'SELECT * FROM events WHERE organizer_email = :email ORDER BY id DESC LIMIT 1',
        ['email' => $input['organizer_email']],
    );
    $events = new PdoEventRepository(db());
    $events->update((int) $event['id'], ['status' => 'changes_requested']);
    $token = (new RevisionTokenService($events))->issue((int) $event['id']);

    $page = request('GET', '/pengajuan/' . $token);
    assertSame(200, $page->status());
    assertSame('no-store', $page->header('Cache-Control'));
    assertSame('no-referrer', $page->header('Referrer-Policy'));
    assertContains('noindex', (string) $page->header('X-Robots-Tag'));

    assertThrows(HttpException::class, fn () => request('GET', '/pengajuan/token-palsu'));
});

test('media access only serves approved non-archived events', function (): void {
    curationFixture();
    $input = submissionInput(['title' => 'Media Uji ' . uniqid()]);
    rawRequest('POST', '/ajukan', $input, [], ['poster' => pngUpload(), 'guidebook' => pdfUpload()]);
    $event = db()->selectOne(
        'SELECT * FROM events WHERE organizer_email = :email ORDER BY id DESC LIMIT 1',
        ['email' => $input['organizer_email']],
    );
    $events = new PdoEventRepository(db());

    assertThrows(HttpException::class, fn () => request('GET', '/media/poster/' . $event['id']));

    $events->update((int) $event['id'], [
        'status' => 'approved',
        'published_at' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:sP'),
    ]);
    $poster = request('GET', '/media/poster/' . $event['id']);
    assertSame(200, $poster->status());
    assertSame('image/png', $poster->header('Content-Type'));

    $events->archive((int) $event['id']);
    assertThrows(HttpException::class, fn () => request('GET', '/media/poster/' . $event['id']));
});
