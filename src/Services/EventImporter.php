<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Repositories\Contracts\CategoryRepository;
use App\Repositories\Contracts\EventRepository;
use App\Repositories\Contracts\ProviderProfileRepository;
use App\Repositories\Contracts\UserRepository;

final class EventImporter
{
    private const CURATION_EMAIL = 'kurasi@internal.invalid';

    public function __construct(
        private readonly Database $database,
        private readonly EventRepository $events,
        private readonly CategoryRepository $categories,
        private readonly ProviderProfileRepository $providers,
        private readonly UserRepository $users,
    ) {
    }

    /**
     * @return array{inserted:int,updated:int,skipped:int,warnings:list<string>,errors:list<string>,dry_run:bool}
     */
    public function import(string $file, bool $dryRun = false): array
    {
        $report = [
            'inserted' => 0,
            'updated' => 0,
            'skipped' => 0,
            'warnings' => [],
            'errors' => [],
            'dry_run' => $dryRun,
        ];

        if (! is_file($file)) {
            $report['errors'][] = 'Berkas tidak ditemukan: ' . $file;

            return $report;
        }

        $decoded = json_decode((string) file_get_contents($file), true);

        if (! is_array($decoded)) {
            $report['errors'][] = 'JSON tidak valid.';

            return $report;
        }

        $entries = array_is_list($decoded) ? $decoded : ($decoded['events'] ?? null);

        if (! is_array($entries)) {
            $report['errors'][] = 'Format harus berupa array atau objek dengan kunci events.';

            return $report;
        }

        $prepared = [];

        foreach ($entries as $index => $entry) {
            $errors = $this->validate($entry, $index + 1);

            if ($errors !== []) {
                $report['errors'] = array_merge($report['errors'], $errors);
                continue;
            }

            $prepared[] = $entry;
        }

        if ($report['errors'] !== []) {
            return $report;
        }

        try {
            $this->database->transaction(function () use ($prepared, &$report): void {
                $profile = $this->ensureCurationProfile();

                foreach ($prepared as $entry) {
                    $error = $this->upsert($entry, (int) $profile['id'], $report);

                    if ($error !== null) {
                        $report['errors'][] = $error;
                    }
                }

                if ($report['errors'] !== [] || $report['dry_run']) {
                    throw new EventImportRollback();
                }
            });
        } catch (EventImportRollback) {
            // Semua perubahan dibatalkan, termasuk saat dry-run.
        }

        return $report;
    }

    /** @return list<string> */
    private function validate(mixed $entry, int $line): array
    {
        if (! is_array($entry)) {
            return ['Entri ' . $line . ' bukan objek.'];
        }

        $errors = [];
        $required = [
            'title', 'slug', 'category', 'organizer', 'description', 'registration_url',
            'source_url', 'source_checked_at', 'mode', 'event_start_at', 'event_end_at',
            'registration_deadline',
        ];

        foreach ($required as $key) {
            if (! isset($entry[$key]) || ! is_string($entry[$key]) || trim($entry[$key]) === '') {
                $errors[] = 'Entri ' . $line . ': ' . $key . ' wajib diisi.';
            }
        }

        if ($errors !== []) {
            return $errors;
        }

        $slug = (string) $entry['slug'];

        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            $errors[] = 'Entri ' . $line . ': slug hanya boleh huruf kecil, angka, dan tanda hubung.';
        }

        if (mb_strlen(trim((string) $entry['title'])) > 180) {
            $errors[] = 'Entri ' . $line . ': judul maksimal 180 karakter.';
        }

        if (mb_strlen(trim((string) $entry['description'])) < 120) {
            $errors[] = 'Entri ' . $line . ': deskripsi minimal 120 karakter.';
        }

        if (! in_array((string) $entry['category'], ['lomba', 'webinar', 'seminar'], true)) {
            $errors[] = 'Entri ' . $line . ': kategori harus lomba, webinar, atau seminar.';
        }

        if (! in_array((string) $entry['mode'], ['online', 'offline', 'hybrid'], true)) {
            $errors[] = 'Entri ' . $line . ': mode tidak valid.';
        }

        if ((string) $entry['mode'] !== 'online'
            && (! isset($entry['location_text']) || trim((string) $entry['location_text']) === '')
        ) {
            $errors[] = 'Entri ' . $line . ': location_text wajib untuk mode offline atau hybrid.';
        }

        foreach (['registration_url', 'source_url'] as $key) {
            if (! $this->validUrl((string) $entry[$key])) {
                $errors[] = 'Entri ' . $line . ': ' . $key . ' harus URL http atau https.';
            }
        }

        if (isset($entry['platform_url']) && $entry['platform_url'] !== ''
            && ! $this->validUrl((string) $entry['platform_url'])
        ) {
            $errors[] = 'Entri ' . $line . ': platform_url harus URL http atau https.';
        }

        $start = $this->parseDate((string) $entry['event_start_at']);
        $end = $this->parseDate((string) $entry['event_end_at']);
        $deadline = $this->parseDate((string) $entry['registration_deadline']);
        $checked = $this->parseDate((string) $entry['source_checked_at']);

        if ($start === null || $end === null || $deadline === null || $checked === null) {
            $errors[] = 'Entri ' . $line . ': tanggal harus format ISO 8601.';

            return $errors;
        }

        if ($end < $start) {
            $errors[] = 'Entri ' . $line . ': event_end_at harus setelah event_start_at.';
        }

        if ($deadline > $start) {
            $errors[] = 'Entri ' . $line . ': registration_deadline harus sebelum event_start_at.';
        }

        if (isset($entry['status']) && ! in_array((string) $entry['status'], ['approved', 'draft'], true)) {
            $errors[] = 'Entri ' . $line . ': status hanya approved atau draft.';
        }

        return $errors;
    }

    /** @param array<string, mixed> $entry @param array<string, mixed> $report */
    private function upsert(array $entry, int $profileId, array &$report): ?string
    {
        $slug = (string) $entry['slug'];
        $category = $this->categories->findBySlug((string) $entry['category']);

        if ($category === null) {
            return 'Entri ' . $slug . ': kategori tidak ditemukan.';
        }

        $existing = $this->events->findBySlug($slug);

        if ($existing !== null && (int) $existing['provider_profile_id'] !== $profileId) {
            return 'Entri ' . $slug . ': slug sudah dimiliki penyedia lain.';
        }

        $status = (string) ($entry['status'] ?? 'approved');
        $publishedAt = $existing['published_at'] ?? null;

        if ($status === 'approved' && $publishedAt === null) {
            $publishedAt = $this->now();
        }

        $attributes = [
            'provider_profile_id' => $profileId,
            'category_id' => (int) $category['id'],
            'title' => trim((string) $entry['title']),
            'slug' => $slug,
            'description' => trim((string) $entry['description']),
            'requirements' => isset($entry['requirements']) ? (string) $entry['requirements'] : null,
            'registration_url' => (string) $entry['registration_url'],
            'mode' => (string) $entry['mode'],
            'location_text' => isset($entry['location_text']) ? (string) $entry['location_text'] : null,
            'platform_url' => isset($entry['platform_url']) ? (string) $entry['platform_url'] : null,
            'event_start_at' => $this->dbDate((string) $entry['event_start_at']),
            'event_end_at' => $this->dbDate((string) $entry['event_end_at']),
            'registration_deadline' => $this->dbDate((string) $entry['registration_deadline']),
            'deadline_confirmed' => ($entry['deadline_confirmed'] ?? true) ? 'true' : 'false',
            'status' => $status,
            'published_at' => $publishedAt,
            'organizer_name' => trim((string) $entry['organizer']),
            'source_url' => (string) $entry['source_url'],
            'source_checked_at' => $this->dbDate((string) $entry['source_checked_at']),
            'source_note' => isset($entry['source_note']) ? (string) $entry['source_note'] : null,
        ];

        if ($existing === null) {
            $this->events->create($attributes);
            $report['inserted']++;
        } else {
            $this->events->update((int) $existing['id'], $attributes);
            $report['updated']++;
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function ensureCurationProfile(): array
    {
        $user = $this->users->findByEmail(self::CURATION_EMAIL);

        if ($user === null) {
            $user = $this->users->create(
                'Tim Kurasi CompHub',
                self::CURATION_EMAIL,
                bin2hex(random_bytes(32)),
                UserRole::Provider,
            );
        }

        $this->users->updateStatus(
            (int) $user['id'],
            AccountStatus::Suspended,
            'Akun sistem kurasi. Tidak untuk login.',
        );

        $profile = $this->providers->findByUserId((int) $user['id']);

        if ($profile === null) {
            $profile = $this->providers->create([
                'user_id' => (int) $user['id'],
                'organization_name' => 'Tim Kurasi CompHub',
                'campus_name' => 'Lintas kampus',
                'official_email' => self::CURATION_EMAIL,
                'description' => 'Akun sistem untuk kegiatan hasil kurasi CompHub.',
            ]);
        }

        if (($profile['verification_status'] ?? '') !== 'verified') {
            $this->providers->update((int) $profile['id'], ['verification_status' => 'verified']);
        }

        return $this->providers->findById((int) $profile['id']) ?? $profile;
    }

    private function validUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }

    private function parseDate(string $value): ?\DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    private function dbDate(string $value): string
    {
        return (new \DateTimeImmutable($value))->format('Y-m-d H:i:sP');
    }

    private function now(): string
    {
        return (new \DateTimeImmutable('now'))->format('Y-m-d H:i:sP');
    }
}
