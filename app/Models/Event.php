<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventMode;
use App\Enums\EventStatus;
use App\Enums\ReportStatus;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kegiatan — entitas inti aplikasi.
 *
 * Di-route memakai `slug`. ID tetap dipakai di URL dashboard supaya penyedia
 * tidak bisa menebak draft milik orang lain lewat enumerate (Panduan §9.2).
 */
#[Fillable([
    'provider_profile_id', 'category_id', 'title', 'slug', 'description',
    'requirements', 'registration_url', 'mode', 'location_text', 'platform_url',
    'event_start_at', 'event_end_at', 'registration_deadline',
    'status', 'risk_score', 'submitted_at', 'published_at',
    'archived_at', 'rejection_reason',
])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mode' => EventMode::class,
            'status' => EventStatus::class,
            'event_start_at' => 'datetime',
            'event_end_at' => 'datetime',
            'registration_deadline' => 'datetime',
            'submitted_at' => 'datetime',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
            'risk_score' => 'integer',
            'view_count' => 'integer',
        ];
    }

    // ------------------------------------------------------------ relasi

    public function providerProfile(): BelongsTo
    {
        return $this->belongsTo(ProviderProfile::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class);
    }

        /**
     * Pengguna yang menyimpan kegiatan ini.
     *
     * HANYA untuk membaca. Jangan pakai `->attach()` / `->sync()` untuk
     * menyimpan: Laravel selalu menyisipkan kolom `updated_at` milik model
     * terhubung ke tabel pivot, sedangkan tabel `bookmarks` sengaja tidak
     * punya kolom itu — sebuah bookmark tidak punya makna "terakhir diubah".
     * Penulisan dilakukan lewat BookmarkRepository yang memakai query
     * builder, dengan INSERT yang diabaikan bila duplikat.
     */
    public function bookmarkedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'bookmarks')
            ->withPivot('created_at');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(EventReport::class);
    }

    public function moderationLogs(): HasMany
    {
        return $this->hasMany(ModerationLog::class);
    }

    /** Laporan yang belum ditangani — dipakai badge di panel admin. */
    public function openReports(): HasMany
    {
        return $this->hasMany(EventReport::class)->where('status', ReportStatus::Open->value);
    }

    /** Jumlah user yang menyimpan; cache agar kartu tidak perlu query. */
    public function bookmarkTotal(): int
    {
        return (int) ($this->attributes['bookmark_count'] ?? $this->bookmarks()->count());
    }

    // ------------------------------------------------------- scope publik

    /**
     * Query dasar daftar publik. Ini satu-satunya tempat status publik
     *didefinisikan, supaya tidak ada controller yang lupa menambahkan filter
     * dan membocorkan draft lewat ID (Panduan FR-03, §6.5).
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', EventStatus::Approved->value);
    }

    /** Published DAN pendaftaran belum ditutup. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->published()->where('registration_deadline', '>=', now());
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('registration_deadline', '<', now());
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->active()->where('event_start_at', '>=', now());
    }

    // -------------------------------------------------------- scope admin

    /** Isi antrean review admin. */
    public function scopeInReviewQueue(Builder $query): Builder
    {
        return $query->whereIn('status', [
            EventStatus::Pending->value,
            EventStatus::ChangesRequested->value,
        ]);
    }

    public function scopeOwnedBy(Builder $query, ProviderProfile $provider): Builder
    {
        return $query->where('provider_profile_id', $provider->id);
    }

    public function scopeOwnedByUser(Builder $query, User $user): Builder
    {
        return $query->whereHas(
            'providerProfile',
            fn (Builder $q) => $q->where('user_id', $user->id),
        );
    }

    // ------------------------------------------------------- scope filter

    /**
     * Pencarian judul + deskripsi. ILIKE dipakai supaya "lomba" menemukan
     * "Lomba" dan "lomba" tanpa perlu dua query.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || trim($term) === '') {
            return $query;
        }

        $term = trim($term);

        return $query->where(function (Builder $q) use ($term): void {
            $q->where('title', 'ilike', "%{$term}%")
                ->orWhere('description', 'ilike', "%{$term}%");
        });
    }

    public function scopeOfCategory(Builder $query, ?int $categoryId): Builder
    {
        return $categoryId === null ? $query : $query->where('category_id', $categoryId);
    }

    public function scopeWithMode(Builder $query, ?EventMode $mode): Builder
    {
        return $mode === null ? $query : $query->where('mode', $mode->value);
    }

    /**
     * Rentang tanggal acara. `null` berarti tidak dibatasi pada sisi itu.
     */
    public function scopeStartingBetween(Builder $query, mixed $from, mixed $to): Builder
    {
        return $query
            ->when($from !== null, fn (Builder $q) => $q->where('event_start_at', '>=', $from))
            ->when($to !== null, fn (Builder $q) => $q->where('event_start_at', '<=', $to));
    }

    public function scopeWithStatus(Builder $query, ?EventStatus $status): Builder
    {
        return $status === null ? $query : $query->where('status', $status->value);
    }

    /**
     * Urutan hasil memakai allowlist. Kolom dan arah TIDAK boleh datang
     * langsung dari input pengguna (Panduan §4.5 — SQL injection lewat
     * query string ORDER BY adalah kelas vuln yang sering terlewat).
     *
     * @var array<string, string>
     */
    private const SORTABLE = [
        'terbaru' => 'published_at',
        'paling_dekat' => 'registration_deadline',
        'acara_dulu' => 'event_start_at',
        'deadline_dulu' => 'registration_deadline',
        'jam_paling_banyak_disimpan' => 'view_count',
    ];

    public function scopeSorted(Builder $query, ?string $sort): Builder
    {
        $column = self::SORTABLE[$sort ?? ''] ?? self::SORTABLE['terbaru'];
        $direction = match ($sort) {
            'acara_dulu', 'deadline_dulu' => 'desc',
            default => 'asc',
        };

        return $query->orderBy($column, $direction)->orderByDesc('id');
    }

    /**
     * @return list<string>
     */
    public static function sortableOptions(): array
    {
        return array_keys(self::SORTABLE);
    }

    // ---------------------------------------------------------- helper

    public function isPublished(): bool
    {
        return $this->status === EventStatus::Approved;
    }

    /** Pendaftaran masih menerima peserta. */
    public function isRegistrationOpen(): bool
    {
        return $this->isPublished() && $this->registration_deadline->isFuture();
    }

    public function isDeadlineSoon(): bool
    {
        return $this->registration_deadline->between(now(), now()->addDays(7));
    }

    /**
     * Sisa hari menuju tenggat. 0 berarti hari ini, negatif berarti lewat.
     */
    public function daysUntilDeadline(): int
    {
        return (int) now()->startOfDay()
            ->diffInDays($this->registration_deadline->startOfDay(), false);
    }

    /**
     * Nama token warna untuk label tenggat. Nilai warna sendiri milik
     * design system UI/UX — model hanya menentukan "level urgensi".
     */
    public function deadlineTone(): string
    {
        if ($this->registration_deadline->isPast()) {
            return 'neutral';
        }

        $days = $this->daysUntilDeadline();

        return match (true) {
            $days <= 1 => 'danger',
            $days <= 7 => 'warning',
            default => 'success',
        };
    }

    /** Label tenggat dalam bahasa manusia. */
    public function deadlineLabel(): string
    {
        $days = $this->daysUntilDeadline();

        return match (true) {
            $days < -1 => 'Sudah lewat '.(-$days).' hari',
            $days === -1 => 'Ditutup kemarin',
            $days === 0 => 'Ditutup hari ini',
            $days === 1 => 'Ditutup besok',
            $days <= 7 => "Ditutup $days hari lagi",
            default => 'Ditutup '.$this->registration_deadline->translatedFormat('d M Y'),
        };
    }

    /** "Online" / nama lokasi. Dipakai di kartu kegiatan. */
    public function locationLabel(): string
    {
        return match ($this->mode) {
            EventMode::Online => 'Daring',
            EventMode::Offline => (string) $this->location_text,
            EventMode::Hybrid => 'Daring + '.((string) $this->location_text),
        };
    }

    public function isOwnedBy(ProviderProfile $provider): bool
    {
        return $this->provider_profile_id === $provider->id;
    }

    /** Apakah penyedia boleh mengedit kegiatan ini di dashboard-nya. */
    public function isEditableByProvider(): bool
    {
        return $this->status->isEditableByProvider();
    }

    public function bookmarkCount(): int
    {
        return $this->bookmarks()->count();
    }

    /**
     * Naikkan penghitung tampilan. Sengaja terpisah supaya bisa dipanggil
     * dari middleware tanpa membuka controller.
     */
    public function registerView(): void
    {
        $this->increment('view_count');
    }

    /**
     * Entri moderasi terakhir — dipakai untuk menampilkan "alasan penolakan"
     * terbaru di dashboard penyedia.
     */
    public function latestModerationLog(): ?ModerationLog
    {
        return $this->moderationLogs()->latest('created_at')->first();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
