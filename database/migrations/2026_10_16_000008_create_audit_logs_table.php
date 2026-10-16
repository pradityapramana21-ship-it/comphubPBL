<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak aksi sensitif (FR-12).
 *
 * SENGAJA TIDAK memakai foreign key ke `users`. Kalau admin dihapus, jejaknya
 * harus tetap ada — inilah alasan tabel ini terpisah dari tabel log lain.
 *
 * Yang TIDAK boleh masuk sini: password, token sesi, kunci API, atau isi
 * lengkap form sensitif. Simpan hanya ringkasan yang dibutuhkan untuk
 * investigasi (Panduan §8: "jangan log password/token").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();

            // Tanpa FK, dengan sengaja. Lihat catatan di atas.
            $table->unsignedBigInteger('actor_user_id')->nullable()
                ->comment('ID pelaku. NULL untuk aksi sistem. Tidak di-FK-kan agar log bertahan.');
            $table->string('actor_role', 20)->nullable()
                ->comment('Disalin saat kejadian, supaya jeaknya tetap terbaca setelah peran berubah.');

            $table->string('action', 60)
                ->comment('event.approved | user.suspended | provider.verified | ...');

            $table->string('target_type', 60)->nullable()->comment('App\Models\Event | App\Models\User | ...');
            $table->unsignedBigInteger('target_id')->nullable();

            $table->jsonb('metadata')->nullable()
                ->comment('Ringkasan perubahan. WAJIB disaring lebih dulu oleh AuditLogService.');

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestampTz('created_at')->useCurrent();
        });

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->index('actor_user_id', 'audit_logs_actor_index');
            $table->index(['target_type', 'target_id'], 'audit_logs_target_index');
            $table->index('action', 'audit_logs_action_index');
            $table->index('created_at', 'audit_logs_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
