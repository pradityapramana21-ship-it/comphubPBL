<?php

declare(strict_types=1);

use App\Enums\EventStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel inti: seluruh informasi kegiatan.
 *
 * Aturan yang ditegakkan di level database (bukan hanya validasi form):
 *  - deadline tidak boleh melewati tanggal acara dimulai
 *  - acara tidak boleh berakhir sebelum dimulai
 *  - kegiatan offline/hybrid wajib punya lokasi
 *  - kegiatan tayang wajib punya waktu publikasi
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('provider_profile_id')
                ->constrained('provider_profiles')
                ->cascadeOnDelete()
                ->comment('Pemilik kegiatan. Menentukan hak akses dan filter "milik saya".');

            $table->foreignId('category_id')
                ->constrained('categories')
                ->restrictOnDelete()
                ->comment('Lomba | Webinar | Seminar');

            $table->string('title', 180);
            $table->string('slug', 200)->unique()->comment('Dipakai di URL publik, harus unik');

            $table->text('description');
            $table->text('requirements')->nullable()
                ->comment('Syarat peserta. Field terpisah supaya halaman detail bisa menampilkannya opsional.');

            // Pendaftaran dilakukan DI LUAR platform (Panduan §1.3).
            // Pratinjau tautan keluar selalu menyertakan peringatan.
            $table->string('registration_url', 500);

            $table->string('mode', 20)->comment('online | offline | hybrid');
            $table->string('location_text', 255)->nullable();
            $table->string('platform_url', 500)->nullable()
                ->comment('Link Zoom/Meet untuk mode online');

            $table->timestampTz('event_start_at');
            $table->timestampTz('event_end_at');
            $table->timestampTz('registration_deadline');

            $table->string('status', 30)->default(EventStatus::Draft->value);
            $table->smallInteger('risk_score')->default(0)
                ->comment('0-100, hasil penjumlahan aturan moderasi');

            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->text('rejection_reason')->nullable()
                ->comment('Alasan penolak atau permintaan perbaikan, dibaca penyedia');

            $table->unsignedInteger('view_count')->default(0);

            $table->timestampsTz();
        });

        DB::statement(
            "ALTER TABLE events ADD CONSTRAINT events_status_check
             CHECK (status IN ('draft','pending','changes_requested','approved','rejected','expired','archived'))"
        );
        DB::statement(
            "ALTER TABLE events ADD CONSTRAINT events_mode_check
             CHECK (mode IN ('online','offline','hybrid'))"
        );
        DB::statement(
            "ALTER TABLE events ADD CONSTRAINT events_risk_score_check
             CHECK (risk_score >= 0 AND risk_score <= 100)"
        );
        DB::statement(
            "ALTER TABLE events ADD CONSTRAINT events_time_order_check
             CHECK (event_end_at >= event_start_at)"
        );
        DB::statement(
            "ALTER TABLE events ADD CONSTRAINT events_deadline_check
             CHECK (registration_deadline <= event_start_at)"
        );
        DB::statement(
            "ALTER TABLE events ADD CONSTRAINT events_location_check
             CHECK (mode = 'online' OR location_text IS NOT NULL)"
        );
        DB::statement(
            "ALTER TABLE events ADD CONSTRAINT events_published_check
             CHECK (published_at IS NOT NULL OR status <> 'approved')"
        );

        // Indeks mengikuti query nyata, bukan semua kolom (Panduan §6.4).
        // 1. Daftar publik: WHERE status='approved' AND registration_deadline >= now()
        //    ORDER BY event_start_at  ->  (status, registration_deadline)
        // 2. Filter kategori: WHERE category_id=? AND status='approved'
        //    ORDER BY event_start_at   ->  (category_id, event_start_at)
        // 3. Dashboard provider: WHERE provider_profile_id=? ORDER BY created_at DESC
        // 4. Dashboard admin: WHERE status IN ('pending','changes_requested')
        Schema::table('events', function (Blueprint $table): void {
            $table->index(['status', 'registration_deadline'], 'events_status_deadline_index');
            $table->index(['category_id', 'event_start_at'], 'events_category_start_index');
            $table->index(['provider_profile_id', 'created_at'], 'events_provider_created_index');
            $table->index(['status', 'risk_score'], 'events_status_risk_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
