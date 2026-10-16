<?php

declare(strict_types=1);

use App\Services\EventExpiryService;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Tugas terjadwal
|--------------------------------------------------------------------------
|
| Jadwal ini adalah lapisan PERTAMA dari dua lapisan yang menutup celah
| kegiatan kedaluwarsa. Lapisan kedua ada di query daftar publik, yang
| menyaring berdasarkan tenggat secara langsung — jadi kegiatan yang
| tenggatnya lewat sudah hilang walau command ini belum sempat jalan
| (Panduan FR-03, TC-DATE-01).
|
| Karena itu command di sini boleh terlambat beberapa menit tanpa
| akibat. Yang tidak boleh telat adalah lapisan kedua.
|
| Menjalankan penjadwal di luar produksi:
|     php artisan schedule:work
|
| Di server, satu cron standard sudah cukup:
|     * * * * * cd /path/ke/revenge && php artisan schedule:run >> /dev/null 2>&1
|
*/

// Tandai kegiatan yang pendaftarannya sudah ditutup.
// Setiap jam, di menit ke-7 supaya tidak bertabrakan dengan backup jam 0.
Schedule::call(function (): void {
    $count = app(EventExpiryService::class)->markExpired();

    if ($count > 0) {
        logger()->info("Menandai {$count} kegiatan sebagai kedaluwarsa.");
    }
})->hourlyAt(7)->name('tandai-kedaluwarsa')->withoutOverlapping();

// Bersihkan penyimpanan kegiatan yang sudah tidak aktif.
// Menjalurkannya setelah langkah di atas supaya keduanya memakai data
// yang sudah konsisten.
Schedule::call(function (): void {
    $removed = app(EventExpiryService::class)->purgeBookmarksOfExpired();

    if ($removed > 0) {
        logger()->info("Membuang {$removed} penyimpanan dari kegiatan yang tidak aktif.");
    }
})->hourlyAt(17)->name('bersihkan-bookmark')->withoutOverlapping();

// Bersihkan cache hitungan. Angka di navbar dibaca setiap halaman, jadi
// cache-nya sengaja berumur pendek tapi tetap perlu disegarkan supaya
// tidak menyimpan angka yang sudah basi selamanya setelah cambie.
Schedule::call(function (): void {
    cache()->forget('admin.stats');
    cache()->forget('admin.reviewQueue.size');
})->everyFiveMinutes()->name('segarkan-cache')->withoutOverlapping();