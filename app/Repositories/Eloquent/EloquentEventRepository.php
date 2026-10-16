<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\DataTransferObjects\CatalogFilter;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use App\Repositories\Contracts\EventRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EloquentEventRepository implements EventRepository
{
    /**
     * Kolom mana yang boleh dipakai untuk ORDER BY.
     *
     * Daftar putih, bukan input mentah. Tanpa ini, `?sort=published_at; DROP
     * TABLE events` akan sampai ke klausa ORDER BY (Panduan §4.5).
     *
     * @var array<string, string>
     */
    private const SORT_COLUMNS = [
        'terbaru' => 'published_at',
        'paling_dekat' => 'registration_deadline',
        'acara_dulu' => 'event_start_at',
        'deadline_dulu' => 'registration_deadline',
        'jam_paling_banyak_disimpan' => 'view_count',
    ];

    public function paginatePublicCatalog(CatalogFilter $filter): LengthAwarePaginator
    {
        return $this->baseQuery($filter)
            ->published()
            ->when($filter->onlyOpen, fn (Builder $q) => $q->where('registration_deadline', '>=', now()))
            ->sorted($filter->sort)
            ->paginate($filter->perPage)
            ->withQueryString();
    }

    public function paginateForProvider(int $providerProfileId, CatalogFilter $filter): LengthAwarePaginator
    {
        return $this->baseQuery($filter)
            ->where('provider_profile_id', $providerProfileId)
            ->withCount('bookmarks')
            ->latest()
            ->paginate($filter->perPage);
    }

    public function paginateForAdmin(CatalogFilter $filter, ?int $providerProfileId = null): LengthAwarePaginator
    {
        return $this->baseQuery($filter)
            // Admin boleh melihat semua status; daftar publik tidak.
            ->when(! $filter->status, fn (Builder $q) => $q->whereIn('status', EventStatus::values()))
            ->when($filter->status, fn (Builder $q) => $q->where('status', $filter->status->value))
            ->when($providerProfileId !== null, fn (Builder $q) => $q->where('provider_profile_id', $providerProfileId))
            ->with(['providerProfile', 'category'])
            ->withCount(['reports as open_reports_count' => fn (Builder $q) => $q->open()])
            ->sorted($filter->sort)
            ->paginate($filter->perPage);
    }

    public function paginateReviewQueue(int $perPage = 20): LengthAwarePaginator
    {
        return Event::query()
            ->inReviewQueue()
            ->with(['providerProfile.user', 'category'])
            ->withCount('openReports')
            ->withCount('bookmarks')
            // Risiko tertinggi dan terlama menunggu di atas.
            ->orderByDesc('risk_score')
            ->orderBy('submitted_at')
            ->paginate(min($perPage, 100));
    }

    public function findPublishedBySlug(string $slug): ?Event
    {
        return Event::query()
            ->published()
            ->where('slug', $slug)
            ->with(['providerProfile', 'category'])
            ->first();
    }

    public function findBySlugAnyStatus(string $slug): ?Event
    {
        return Event::query()
            ->where('slug', $slug)
            ->with(['providerProfile', 'category'])
            ->first();
    }

    public function findById(int $id): ?Event
    {
        return Event::query()->with(['providerProfile', 'category'])->find($id);
    }

    public function findOwnedByProvider(int $id, int $providerProfileId): ?Event
    {
        return Event::query()
            ->whereKey($id)
            ->where('provider_profile_id', $providerProfileId)
            ->with('category')
            ->first();
    }

    public function create(array $attributes): Event
    {
        return Event::create($attributes);
    }

    public function update(int $id, array $attributes): Event
    {
        $event = Event::findOrFail($id);
        $event->fill($attributes)->save();

        return $event->refresh();
    }

    public function incrementViewCount(int $id): void
    {
        // satu operasi SQL, tanpa read-modify-write di aplikasi
        Event::query()->whereKey($id)->increment('view_count');
    }

    public function countSubmissionsSince(int $providerProfileId, \DateTimeInterface $since): int
    {
        return Event::query()
            ->where('provider_profile_id', $providerProfileId)
            ->whereNotNull('submitted_at')
            ->where('submitted_at', '>=', $since)
            ->count();
    }

    public function findPossibleDuplicates(
        int $providerProfileId,
        string $title,
        \DateTimeInterface $startDate,
    ): iterable {
        $needle = mb_strtolower(trim($title));

        return Event::query()
            ->where('provider_profile_id', $providerProfileId)
            // Toleransi 3 hari: kegiatan yang sama bisa bergeser tanggal.
            ->whereBetween('event_start_at', [
                (clone $startDate)->modify('-3 days'),
                (clone $startDate)->modify('+3 days'),
            ])
            ->where('id', '!=', 0)
            ->get()
            ->filter(fn (Event $candidate): bool => $this->titleSimilarity($needle, mb_strtolower((string) $candidate->title)) >= 0.85)
            ->values();
    }

    public function incrementBookmarkCount(int $id): void
    {
        Event::query()->whereKey($id)->increment('bookmark_count');
    }

    public function decrementBookmarkCount(int $id): void
    {
        // GREATEST(..., 0) menjaga angka tidak pernah turun ke negatif.
        Event::query()
            ->whereKey($id)
            ->update(['bookmark_count' => \DB::raw('GREATEST(bookmark_count - 1, 0)')]);
    }

    public function countAll(): int
    {
        return Event::query()->count();
    }

    public function countByStatus(): array
    {
        return Event::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();
    }

    // ----------------------------------------------------------- internal

    /**
     * Query dasar yang dipakai tiga caller. Satu titik ini yang memastikan
     * filter tidak pernah lupa diterapkan di salah satu halaman.
     */
    private function baseQuery(CatalogFilter $filter): Builder
    {
        return Event::query()
            ->search($filter->keyword)
            ->ofCategory($filter->categoryId)
            ->withMode($filter->mode)
            ->startingBetween($filter->startsFrom, $filter->startsUntil)
            ->when($filter->userId !== null, fn (Builder $q) => $q->ownedByUser(
                User::findOrFail($filter->userId),
            ));
    }

    /**
     * Kemiripan karakter dua judul, 0..1.
     *
     * Dipakai aturan duplikasi. Levenshtein murni kurang berguna untuk judul
     * yang disusun ulang ("Lomba C" vs "C Lomba"), jadi digabung dengan
     * kesamaan kata.
     */
    private function titleSimilarity(string $a, string $b): float
    {
        if ($a === '' || $b === '') {
            return 0.0;
        }

        if ($a === $b) {
            return 1.0;
        }

        $distance = levenshtein($a, $b);
        $maxLength = max(mb_strlen($a), mb_strlen($b));
        $characterScore = 1.0 - ($distance / $maxLength);

        $wordsA = array_unique(preg_split('/\W+/u', $a) ?: []);
        $wordsB = array_unique(preg_split('/\W+/u', $b) ?: []);
        $wordsA = array_filter($wordsA);
        $wordsB = array_filter($wordsB);
        $union = array_unique(array_merge($wordsA, $wordsB));
        $wordScore = $union === [] ? 0.0 : (count(array_intersect($wordsA, $wordsB)) / count($union));

        // Kata lebih berat: "lomba nasional 2026" vs "lomba nasional 2026"
        // harus dianggap kembar walau urutan karakternya beda.
        return ($characterScore * 0.4) + ($wordScore * 0.6);
    }
}
