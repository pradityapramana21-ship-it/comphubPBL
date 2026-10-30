<?php

declare(strict_types=1);

use App\Core\Database;

function schemaFixture(Database $db): array
{
    $email = 'schema-' . bin2hex(random_bytes(4)) . '@example.test';
    $userId = $db->insert(
        'INSERT INTO users (name, email, password) VALUES (:name, :email, :password) RETURNING id',
        ['name' => 'Schema Tester', 'email' => $email, 'password' => password_hash('secret123', PASSWORD_DEFAULT)],
    );
    $providerId = $db->insert(
        'INSERT INTO provider_profiles (user_id, organization_name, campus_name, official_email)
         VALUES (:user_id, :org, :campus, :email) RETURNING id',
        ['user_id' => $userId, 'org' => 'Org Schema', 'campus' => 'Kampus Schema', 'email' => 'org@example.test'],
    );
    $categoryId = (int) $db->selectOne('SELECT id FROM categories ORDER BY id LIMIT 1')['id'];

    return [$userId, $providerId, $categoryId];
}

function insertEvent(Database $db, array $fixture, array $overrides = []): int
{
    [$userId, $providerId, $categoryId] = $fixture;
    $data = array_merge([
        'provider_profile_id' => $providerId,
        'category_id' => $categoryId,
        'title' => 'Kegiatan Uji Skema Nasional',
        'slug' => 'kegiatan-uji-' . bin2hex(random_bytes(6)),
        'description' => str_repeat('Deskripsi kegiatan yang jelas. ', 6),
        'registration_url' => 'https://example.test/daftar',
        'mode' => 'online',
        'location_text' => null,
        'platform_url' => 'https://example.test/zoom',
        'event_start_at' => '2027-01-10 09:00:00+07',
        'event_end_at' => '2027-01-10 12:00:00+07',
        'registration_deadline' => '2027-01-05 23:59:00+07',
        'status' => 'draft',
    ], $overrides);

    return $db->insert(
        'INSERT INTO events (
            provider_profile_id, category_id, title, slug, description, registration_url,
            mode, location_text, platform_url, event_start_at, event_end_at, registration_deadline, status
         ) VALUES (
            :provider_profile_id, :category_id, :title, :slug, :description, :registration_url,
            :mode, :location_text, :platform_url, :event_start_at, :event_end_at, :registration_deadline, :status
         ) RETURNING id',
        $data,
    );
}

test('schema adds event metadata columns and topic categories additively', function (): void {
    $db = db();
    $columns = [];

    foreach ($db->select(
        "SELECT column_name FROM information_schema.columns
         WHERE table_schema = current_schema() AND table_name = 'events'",
    ) as $row) {
        $columns[] = (string) $row['column_name'];
    }

    foreach ([
        'event_type', 'registration_start_at', 'poster_path', 'guidebook_path',
        'organizer_email', 'contact_number', 'revision_token_hash',
        'revision_token_expires_at', 'revision_token_revoked_at',
        'revision_token_last_used_at',
    ] as $column) {
        assertTrue(in_array($column, $columns, true), 'Missing events column: ' . $column);
    }

    $categories = $db->select('SELECT slug, is_active FROM categories ORDER BY slug');
    assertSame(7, count($categories));

    $active = [];

    foreach ($categories as $category) {
        if ((bool) $category['is_active']) {
            $active[] = (string) $category['slug'];
        }
    }

    sort($active);
    assertSame(['akademik', 'bisnis', 'teknologi', 'umum'], $active);
    assertFalse((bool) $db->selectOne("SELECT is_active FROM categories WHERE slug = 'lomba'")['is_active']);
    assertFalse((bool) $db->selectOne("SELECT is_active FROM categories WHERE slug = 'webinar'")['is_active']);
    assertFalse((bool) $db->selectOne("SELECT is_active FROM categories WHERE slug = 'seminar'")['is_active']);
});

test('schema rejects invalid event dates and missing offline location', function (): void {
    $db = db();
    $pdo = $db->pdo();
    $pdo->beginTransaction();

    try {
        $fixture = schemaFixture($db);

        $cases = [
            ['event_end_at' => '2027-01-09 10:00:00+07'],
            ['registration_deadline' => '2027-01-11 10:00:00+07'],
            ['mode' => 'offline', 'location_text' => null],
            ['risk_score' => 101],
        ];

        foreach ($cases as $overrides) {
            $pdo->exec('SAVEPOINT invalid_case');

            try {
                insertEvent($db, $fixture, $overrides);
                throw new RuntimeException('Constraint did not reject: ' . json_encode($overrides));
            } catch (PDOException) {
                $pdo->exec('ROLLBACK TO SAVEPOINT invalid_case');
            }
        }

        $validId = insertEvent($db, $fixture);
        assertTrue($validId > 0);

        $pdo->rollBack();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
});

test('approved event requires published_at', function (): void {
    $db = db();
    $pdo = $db->pdo();
    $pdo->beginTransaction();

    try {
        $fixture = schemaFixture($db);
        $pdo->exec('SAVEPOINT approved_case');

        try {
            insertEvent($db, $fixture, ['status' => 'approved']);
            throw new RuntimeException('approved without published_at was accepted');
        } catch (PDOException) {
            $pdo->exec('ROLLBACK TO SAVEPOINT approved_case');
        }

        $pdo->rollBack();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
});
