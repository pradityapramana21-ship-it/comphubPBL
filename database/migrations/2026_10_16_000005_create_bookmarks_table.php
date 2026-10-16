<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fitur "Simpan kegiatan".
 *
 * PRIMARY KEY komposit (user_id, event_id) adalah solusi terbaik untuk
 * kebutuhan FR-05 "pasangan user_id + event_id tidak boleh duplikat": tidak
 * perlu cek-then-insert di aplikasi, dan dua klik bersamaan dari user yang
 * sama akan ditolak database — bukan menghasilkan dua baris (Panduan §9.2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookmarks', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->primary(['user_id', 'event_id'], 'bookmarks_primary');
        });

        // Halaman "Tersimpan": SELECT ... WHERE user_id=? ORDER BY created_at DESC.
        // Indeks di (user_id) sudah terpenuhi oleh prefix PK komposit, tapi
        // event_id perlu indeks sendiri untuk hitung "disimpan berapa kali".
        Schema::table('bookmarks', function (Blueprint $table): void {
            $table->index('event_id', 'bookmarks_event_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookmarks');
    }
};
