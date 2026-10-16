<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat keputusan moderasi — otomatis maupun manual.
 *
 * Tabel ini menjawab pertanyaan "kenapa kegiatan ini ditahan?" yang harus
 * bisa dilihat admin di antrean review (Panduan §7.2: "Mencatat alasan
 * aturan yang terpicu (rule key) agar admin mengetahui mengapa sebuah
 * submission ditandai").
 *
 * TIDAK punya updated_at: log bersifat append-only. Perbaikan dilakukan
 * dengan baris baru, bukan mengubah riwayat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moderation_logs', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('event_id')
                ->constrained('events')
                ->cascadeOnDelete();

            // NULL untuk entri otomatis (sistem yang jadi pelaku).
            $table->foreignId('actor_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Admin yang mengambil keputusan. NULL = otomatis oleh sistem.');

            $table->string('action', 30)->comment('submit | auto_flagged | auto_rejected | approve | reject | ...');

            // Kunci aturan yang memicu, mis. "duplicate" atau "keyword_blacklist".
            // Separate dari `reason` (teks bebas) supaya bisa dikelompokkan.
            $table->string('rule_key', 50)->nullable();
            $table->text('reason')->nullable()->comment('Alasan dalam bahasa manusia');

            $table->smallInteger('risk_score')->nullable()
                ->comment('Skor total pada saat entri ini dibuat');

            $table->jsonb('metadata')->nullable()
                ->comment('Rincian: nilai yang cocok, ambang batas, nama aturan');

            $table->timestampTz('created_at')->useCurrent();
        });

        Schema::table('moderation_logs', function (Blueprint $table): void {
            $table->index(['event_id', 'created_at'], 'moderation_logs_event_created_index');
            $table->index(['rule_key'], 'moderation_logs_rule_key_index');
            $table->index(['action', 'created_at'], 'moderation_logs_action_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_logs');
    }
};
