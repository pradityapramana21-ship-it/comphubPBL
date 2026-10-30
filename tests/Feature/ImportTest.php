<?php

declare(strict_types=1);

use App\Repositories\Pdo\PdoEventRepository;
use App\Services\EventImporter;

function importerPayload(string $slug, array $overrides = []): array
{
    return $overrides + [
        'title' => 'Kegiatan Impor ' . $slug,
        'slug' => $slug,
        'category' => 'lomba',
        'organizer' => 'Penyelenggara Impor',
        'description' => 'Deskripsi kegiatan impor yang cukup panjang untuk memenuhi batas minimum kurasi. Informasi ini berasal dari sumber resmi penyelenggara dan sudah diperiksa sebelum tayang.',
        'requirements' => 'Mahasiswa aktif.',
        'registration_url' => 'https://daftar.example.test/' . $slug,
        'source_url' => 'https://sumber.example.test/' . $slug,
        'source_checked_at' => (new DateTimeImmutable('-1 hour'))->format('Y-m-d H:i:sP'),
        'source_note' => 'Sumber resmi penyelenggara.',
        'mode' => 'online',
        'event_start_at' => (new DateTimeImmutable('+10 days'))->format('Y-m-d H:i:sP'),
        'event_end_at' => (new DateTimeImmutable('+10 days +2 hours'))->format('Y-m-d H:i:sP'),
        'registration_deadline' => (new DateTimeImmutable('+5 days'))->format('Y-m-d H:i:sP'),
        'deadline_confirmed' => true,
        'status' => 'approved',
    ];
}

function importerFile(array $entries): string
{
    $file = tempnam(sys_get_temp_dir(), 'comphub-import-');

    if ($file === false) {
        throw new RuntimeException('Gagal membuat berkas sementara.');
    }

    file_put_contents($file, json_encode($entries, JSON_THROW_ON_ERROR));

    return $file;
}

test('event importer rolls back dry-run and updates an existing slug in place', function (): void {
    $slug = 'impor-uji-' . uniqid();
    $file = importerFile([importerPayload($slug)]);
    $importer = app()->container()->make(EventImporter::class);
    $events = new PdoEventRepository(db());

    try {
        $dry = $importer->import($file, true);
        assertSame(1, $dry['inserted']);
        assertTrue($events->findBySlug($slug) === null);

        $real = $importer->import($file);
        assertSame(1, $real['inserted']);
        $event = $events->findBySlug($slug);
        assertTrue($event !== null);
        assertSame('https://sumber.example.test/' . $slug, $event['source_url']);
        assertSame('verified', $event['provider_verification_status']);
        assertSame('competition', $event['event_type']);
        assertSame('teknologi', $event['category_slug']);

        $again = $importer->import($file);
        assertSame(1, $again['updated']);
        assertSame(1, (int) db()->selectOne(
            'SELECT count(*) AS total FROM events WHERE slug = :slug',
            ['slug' => $slug],
        )['total']);
    } finally {
        @unlink($file);
    }
});

test('event importer rejects an entry without a source url and writes nothing', function (): void {
    $slug = 'impor-tolak-' . uniqid();
    $file = importerFile([importerPayload($slug, ['source_url' => ''])]);
    $importer = app()->container()->make(EventImporter::class);
    $events = new PdoEventRepository(db());

    try {
        $report = $importer->import($file);
        assertTrue($report['errors'] !== []);
        assertSame(0, $report['inserted']);
        assertTrue($events->findBySlug($slug) === null);
    } finally {
        @unlink($file);
    }
});
