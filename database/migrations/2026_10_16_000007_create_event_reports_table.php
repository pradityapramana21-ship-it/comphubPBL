<?php

declare(strict_types=1);

use App\Enums\ReportReason;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pelaporan kegiatan oleh pengguna (FR-11).
 *
 * Privacy: identitas pelapor tidak boleh tampil ke publik maupun ke penyedia
 * kegiatan — hanya admin yang boleh melihat (Panduan §4.5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_reports', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('event_id')
                ->constrained('events')
                ->cascadeOnDelete();

            $table->foreignId('reporter_user_id')
                ->constrained('users')
                ->cascadeOnDelete()
                ->comment('Rahasia dari penyedia kegiatan. Hanya admin yang boleh melihat.');

            $table->string('reason_code', 30)->default(ReportReason::Other->value);
            $table->text('description')->nullable();

            $table->string('status', 20)->default('open')
                ->comment('open | reviewing | resolved | dismissed');
            $table->text('resolution_note')->nullable();
            $table->foreignId('resolved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestampTz('resolved_at')->nullable();

            $table->timestampsTz();

            // Cegah pengguna yang sama melaporkan kegiatan yang sama berulang-ulang.
            $table->unique(['event_id', 'reporter_user_id'], 'event_reports_unique_reporter');
        });

        DB::statement(
            "ALTER TABLE event_reports ADD CONSTRAINT event_reports_reason_check
             CHECK (reason_code IN ('misleading_info','broken_link','spam','scam','duplicate','other'))"
        );
        DB::statement(
            "ALTER TABLE event_reports ADD CONSTRAINT event_reports_status_check
             CHECK (status IN ('open','reviewing','resolved','dismissed'))"
        );

        // Antrean laporan: WHERE status='open' ORDER BY created_at.
        Schema::table('event_reports', function (Blueprint $table): void {
            $table->index(['status', 'created_at'], 'event_reports_status_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_reports');
    }
};
