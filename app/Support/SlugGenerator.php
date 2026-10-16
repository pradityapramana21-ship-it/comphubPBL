<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Membuat slug unik untuk URL publik.
 *
 * Slug masuk ke URL yang disharing orang, jadi harus stabil dan enak dibaca:
 * "lomba-nasional-pemrograman-2026", bukan "37".
 *
 * Keunikan dijamin dengan query COUNT, bukan dengan suffix acak yang
 * panjang. Kalau acak, tautan yang sudah dibagikan orang jadi rusak
 * begitu ada kegiatan serupa.
 */
final class SlugGenerator
{
    private const MAX_LENGTH = 180;

    /**
     * Bangun slug dari sebuah judul, dijamin belum dipakai tabel $table.
     */
    public function unique(string $source, string $table, ?int $ignoreId = null): string
    {
        $base = $this->base($source);

        if ($base === '') {
            $base = 'kegiatan';
        }

        $candidate = $base;
        $suffix = 2;

        while ($this->exists($table, $candidate, $ignoreId)) {
            $candidate = $this->appendSuffix($base, $suffix);
            $suffix++;
        }

        return $candidate;
    }

    /**
     * Bentuk dasar slug, tanpa mengecek ketersediaan.
     */
    public function base(string $source): string
    {
        $slug = Str::slug($source);

        if (mb_strlen($slug) > self::MAX_LENGTH) {
            // Potong di batas kata, bukan di tengah kata.
            $truncated = mb_substr($slug, 0, self::MAX_LENGTH);
            $lastDash = mb_strrpos($truncated, '-');

            if ($lastDash !== false && $lastDash > 20) {
                $truncated = mb_substr($truncated, 0, $lastDash);
            }

            $slug = trim($truncated, '-');
        }

        return $slug;
    }

    /**
     * @param  list<string>  $reserved  slug yang tidak boleh dipakai walau belum ada di database
     */
    public function isReserved(string $slug, array $reserved = []): bool
    {
        return in_array($slug, $reserved, true);
    }

    private function exists(string $table, string $slug, ?int $ignoreId): bool
    {
        // $table berasal dari kode, bukan dari input pengguna, jadi aman
        // untuk dipakai langsung sebagai nama tabel di query builder.
        return DB::table($table)
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();
    }

    private function appendSuffix(string $base, int $suffix): string
    {
        $room = self::MAX_LENGTH - mb_strlen((string) $suffix) - 1;
        $trimmed = mb_substr($base, 0, max(10, $room));

        return $trimmed . '-' . $suffix;
    }
}
