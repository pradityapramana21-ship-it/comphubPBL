<?php

declare(strict_types=1);

namespace App\Support;

use App\Repositories\Contracts\CategoryRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Daftar kategori aktif, diambil dari cache.
 *
 * Kenapa bukan cache yang biasa?
 *
 * Laravel 13 sengaja menolak membuat objek PHP apa pun saat
 * membaca cache:
 *
 *     'serializable_classes' => false,   // config/cache.php
 *
 * Itu tindakan keamanan yang benar — tujuannya mencegah gadget chain
 * kalau APP_KEY bocor. Tapi artinya Eloquent Collection atau model
 * TIDAK BOLEH disimpan di cache: pembacaan berikutnya mengembalikan
 * `__PHP_Incomplete_Class`, dan setiap halaman yang memakainya akan
 * balas 500.
 *
 * Jadi yang disimpan di sini hanya baris data biasa: id, nama, slug,
 * dan deskripsi. Yang kembali ke view adalah kumpulan objek ringan.
 * Tidak ada bedanya bagi view, karena semua yang dipakai view memang
 * hanya kolom-kolom itu.
 */
class CategoryCache
{
    private const KEY = 'catalog.categories';

    private const TTL_MINUTES = 30;

    /**
     * @return Collection<int, object{id: int, name: string, slug: string, description: ?string}>
     */
    public function all(): Collection
    {
        $rows = Cache::remember(
            self::KEY,
            now()->addMinutes(self::TTL_MINUTES),
            function (): array {
                try {
                    return app(CategoryRepository::class)
                        ->allActive()
                        ->map(fn ($category): array => [
                            'id' => $category->id,
                            'name' => $category->name,
                            'slug' => $category->slug,
                            'description' => $category->description,
                        ])
                        ->all();
                } catch (\Throwable $e) {
                    // Kalau database belum siap, halaman statis tetap
                    // harus bisa dibuka. Menu filter kosong lebih baik
                    // daripada halaman putih.
                    Log::warning('Gagal memuat kategori untuk cache: '.$e->getMessage());

                    return [];
                }
            },
        );

        return collect($rows)->map(static fn (array $row): object => (object) $row);
    }

    /** Dipanggil setiap kali kategori berubah. */
    public function forget(): void
    {
        Cache::forget(self::KEY);

        // Kunci lama masih mungkin tertinggal di database development
        // yang belum dimigrasi; membersihkannya sekali saja murah.
        Cache::forget('view.categories');
    }
}