<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Penghitung jumlah penyimpanan pada tabel `events`.
 *
 * Denormalisasi yang disengaja: kartu kegiatan menampilkan angka ini di
 * setiap kartu, dan menghitungnya live berarti satu query COUNT per kartu.
 * Sumber kebenaran tetap tabel `bookmarks`; kolom ini hanya cache yang
 * diperbarui saat bookmark ditambah/dihapus.
 *
 * Migration terpisah karena `events` sudah punya data — mengubah migration
 * yang sudah dijalankan melanggar Panduan §5.1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->unsignedInteger('bookmark_count')->default(0)->after('view_count');
        });

        // Isi ulang dari sumber kebenaran, jangan mulai dari nol.
        DB::statement(
            'UPDATE events SET bookmark_count = sub.total
             FROM (SELECT event_id, count(*) AS total FROM bookmarks GROUP BY event_id) AS sub
             WHERE events.id = sub.event_id'
        );

        // Raum untuk mengurutkan berdasarkan popularitas tanpa perlu join.
        DB::statement(
            'CREATE INDEX events_bookmark_count_index ON events (bookmark_count DESC)'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS events_bookmark_count_index');
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('bookmark_count');
        });
    }
};
