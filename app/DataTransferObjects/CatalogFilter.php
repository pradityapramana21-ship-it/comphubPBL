<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Enums\EventMode;
use App\Enums\EventStatus;

/**
 * Parameter pencarian katalog publik.
 *
 * Immutable: objek ini hanya dibuat sekali dari query string, lalu diteruskan
 * ke repository. Setelah itu tidak boleh diubah — supaya kondisi query yang
 * dipakai audit log sama persis dengan yang dipakai menampilkan hasil.
 */
final readonly class CatalogFilter
{
    public function __construct(
        public ?string $keyword = null,
        public ?int $categoryId = null,
        public ?EventMode $mode = null,
        public ?\DateTimeInterface $startsFrom = null,
        public ?\DateTimeInterface $startsUntil = null,
        /** Hanya tampilkan kegiatan yang tenggatnya belum lewat. */
        public bool $onlyOpen = true,
        public ?EventStatus $status = null,
        public ?int $providerProfileId = null,
        public ?int $userId = null,
        public string $sort = 'terbaru',
        public int $perPage = 12,
    ) {}

    /**
     * Bangun dari array query string.
     *
     * Sengaja TIDAK memvalidasi lewat Validator: filter ini bukan input form,
     * dan setiap nilai sudah dibatasi oleh parser di bawah (enum, tanggal,
     * allowlist). Nilai yang tidak dikenal dibuang, bukan diteruskan.
     *
     * @param  array<string, mixed>  $query
     */
    public static function fromQuery(array $query): self
    {
        return new self(
            keyword: self::cleanKeyword($query['q'] ?? null),
            categoryId: self::positiveInt($query['category'] ?? null),
            mode: self::parseMode($query['mode'] ?? null),
            startsFrom: self::parseDate($query['from'] ?? null),
            startsUntil: self::parseDate($query['until'] ?? null),
            onlyOpen: self::parseBool($query['open'] ?? null, default: true),
            status: self::parseStatus($query['status'] ?? null),
            sort: self::parseSort($query['sort'] ?? null),
            perPage: self::clampPerPage($query['per_page'] ?? null),
        );
    }

    /**
     * Untuk daftar_admin/penyedia, batas tenggat dimatikan supaya kegiatan
     * yang sudah lewat tetap terlihat dan bisa diarsipkan.
     */
    public function withoutDeadlineFilter(): self
    {
        return new self(
            $this->keyword, $this->categoryId, $this->mode,
            $this->startsFrom, $this->startsUntil, false,
            $this->status, $this->providerProfileId, $this->userId,
            $this->sort, $this->perPage,
        );
    }

    public function forProvider(int $providerProfileId): self
    {
        return new self(
            $this->keyword, $this->categoryId, $this->mode,
            $this->startsFrom, $this->startsUntil, false,
            $this->status, $providerProfileId, $this->userId,
            $this->sort, $this->perPage,
        );
    }

    public function forUser(int $userId): self
    {
        return new self(
            $this->keyword, $this->categoryId, $this->mode,
            $this->startsFrom, $this->startsUntil, false,
            $this->status, null, $userId,
            $this->sort, $this->perPage,
        );
    }

    /** Ringkasan filter aktif, ditampilkan di UI sebagai chip yang bisa dihapus. */
    public function isEmpty(): bool
    {
        return $this->keyword === null
            && $this->categoryId === null
            && $this->mode === null
            && $this->startsFrom === null
            && $this->startsUntil === null;
    }

    // ----------------------------------------------------------- parsing

    private static function cleanKeyword(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return $value === '' ? null : mb_substr($value, 0, 100);
    }

    private static function positiveInt(mixed $value): ?int
    {
        $int = filter_var($value, FILTER_VALIDATE_INT);

        return ($int === false || $int <= 0) ? null : $int;
    }

    private static function parseMode(mixed $value): ?EventMode
    {
        return is_string($value) ? EventMode::tryFrom($value) : null;
    }

    private static function parseStatus(mixed $value): ?EventStatus
    {
        return is_string($value) ? EventStatus::tryFrom($value) : null;
    }

    private static function parseDate(mixed $value): ?\DateTimeInterface
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable(trim($value));
        } catch (\Exception) {
            // Tanggal rusak berarti "tanpa batas", bukan error 500 —
            // orang yang mengetik tidak sengaja tidak boleh melihat halaman rusak.
            return null;
        }
    }

    private static function parseBool(mixed $value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    private static function parseSort(mixed $value): string
    {
        $allowed = ['terbaru', 'paling_dekat', 'acara_dulu', 'deadline_dulu', 'jam_paling_banyak_disimpan'];

        return is_string($value) && in_array($value, $allowed, true) ? $value : 'terbaru';
    }

    /** Batas atas tetap: mencegah satu permintaan mengambil seluruh tabel. */
    private static function clampPerPage(mixed $value): int
    {
        $int = self::positiveInt($value);

        return min($int ?? 12, 48);
    }
}
