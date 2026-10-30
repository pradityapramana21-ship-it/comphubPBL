<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\Contracts\EventRepository;
use App\Support\SlugGenerator;

final class EventSubmissionService
{
    public function __construct(
        private readonly EventRepository $events,
        private readonly CurationProfileService $curation,
        private readonly UploadService $uploads,
        private readonly SlugGenerator $slugs,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $poster
     * @param array<string, mixed>|null $guidebook
     * @return array<string, mixed>
     */
    public function submit(array $data, array $poster, ?array $guidebook): array
    {
        $posterPath = $this->uploads->store($poster, 'poster');
        $guidebookPath = $guidebook !== null ? $this->uploads->store($guidebook, 'guidebook') : null;

        try {
            return $this->events->create($this->attributes($data, $posterPath, $guidebookPath) + [
                'slug' => $this->slugs->unique((string) $data['title'], 'events'),
                'status' => 'pending',
                'submitted_at' => (new \DateTimeImmutable('now'))->format('Y-m-d H:i:sP'),
                'published_at' => null,
                'risk_score' => 0,
            ]);
        } catch (\Throwable $exception) {
            $this->uploads->remove($posterPath);
            $this->uploads->remove($guidebookPath);

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $poster
     * @param array<string, mixed>|null $guidebook
     * @return array<string, mixed>
     */
    public function revise(array $event, array $data, ?array $poster, ?array $guidebook): array
    {
        $posterPath = $poster !== null ? $this->uploads->store($poster, 'poster') : null;
        $guidebookPath = $guidebook !== null ? $this->uploads->store($guidebook, 'guidebook') : null;

        try {
            $updated = $this->events->update((int) $event['id'], $this->attributes($data, $posterPath, $guidebookPath) + [
                'status' => 'pending',
                'submitted_at' => (new \DateTimeImmutable('now'))->format('Y-m-d H:i:sP'),
                'rejection_reason' => null,
                'revision_token_revoked_at' => (new \DateTimeImmutable('now'))->format('Y-m-d H:i:sP'),
            ]);
        } catch (\Throwable $exception) {
            $this->uploads->remove($posterPath);
            $this->uploads->remove($guidebookPath);

            throw $exception;
        }

        if ($posterPath !== null) {
            $this->uploads->remove((string) ($event['poster_path'] ?? ''));
        }

        if ($guidebookPath !== null) {
            $this->uploads->remove((string) ($event['guidebook_path'] ?? ''));
        }

        return $updated;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function attributes(array $data, ?string $posterPath, ?string $guidebookPath): array
    {
        $attributes = [
            'provider_profile_id' => $this->curation->id(),
            'category_id' => (int) $data['category_id'],
            'title' => trim((string) $data['title']),
            'description' => trim((string) $data['description']),
            'requirements' => trim((string) ($data['requirements'] ?? '')) ?: null,
            'registration_url' => trim((string) $data['registration_url']),
            'mode' => (string) $data['mode'],
            'location_text' => trim((string) ($data['location_text'] ?? '')) ?: null,
            'platform_url' => trim((string) ($data['platform_url'] ?? '')) ?: null,
            'event_start_at' => (string) $data['event_start_at'],
            'event_end_at' => (string) $data['event_end_at'],
            'registration_start_at' => trim((string) ($data['registration_start_at'] ?? '')) ?: null,
            'registration_deadline' => (string) $data['registration_deadline'],
            'deadline_confirmed' => true,
            'organizer_name' => trim((string) $data['organizer_name']),
            'organizer_email' => mb_strtolower(trim((string) $data['organizer_email'])),
            'contact_number' => trim((string) $data['contact_number']),
            'event_type' => (string) $data['event_type'],
            'source_url' => trim((string) ($data['source_url'] ?? '')) ?: trim((string) $data['registration_url']),
            'source_checked_at' => (new \DateTimeImmutable('now'))->format('Y-m-d H:i:sP'),
            'source_note' => 'Diajukan penyelenggara melalui formulir publik.',
        ];

        if ($posterPath !== null) {
            $attributes['poster_path'] = $posterPath;
        }

        if ($guidebookPath !== null) {
            $attributes['guidebook_path'] = $guidebookPath;
        }

        return $attributes;
    }
}
