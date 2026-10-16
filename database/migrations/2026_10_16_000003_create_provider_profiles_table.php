<?php

declare(strict_types=1);

use App\Enums\VerificationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Profil organisasi penyedia (BEM, himpunan, UKM, fakultas, kampus lain).
 *
 * Satu akun = satu profil MVP (Panduan §6.3). Kalau nanti satu organisasi
 * butuh beberapa akun, tambahkan tabel `provider_memberships` — JANGAN
 * diubah ke sini, karena data yang sudah dipakai tidak boleh dirombak diam-diam.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_profiles', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete()
                ->comment('Satu akun hanya boleh punya satu profil organisasi');

            $table->string('organization_name', 150);
            $table->string('campus_name', 150)->comment('Kampus asal, boleh di luar kampus kita');
            $table->string('official_email', 190);
            $table->string('website_url')->nullable();
            $table->text('description')->nullable();

            $table->string('verification_status', 20)
                ->default(VerificationStatus::Pending->value)
                ->comment('pending | verified | rejected');

            // Bukti organisasi. Berkas SENGAJA disimpan di luar webroot
            // (storage/app/private) — Panduan §8.
            $table->string('verification_document_path')->nullable()
                ->comment('Path relatif di disk private. Tidak pernah disimpan di /public.');

            $table->text('verification_note')->nullable()
                ->comment('Alasan ditolak atau catatan permintaan verifikasi');

            $table->timestampTz('verified_at')->nullable();
            $table->foreignId('verified_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Admin yang memverifikasi');

            $table->timestampsTz();
        });

        DB::statement(
            "ALTER TABLE provider_profiles ADD CONSTRAINT provider_profiles_verification_check
             CHECK (verification_status IN ('pending', 'verified', 'rejected'))"
        );
        // Tanggal verifikasi harus ada kalau statusnya sudah verified,
        // dan tidak boleh ada kalau masih pending.
        DB::statement(
            "ALTER TABLE provider_profiles ADD CONSTRAINT provider_profiles_verified_at_check
             CHECK ((verification_status = 'verified') = (verified_at IS NOT NULL))"
        );

        // Antrean review admin: SELECT * WHERE verification_status = 'pending'
        // ORDER BY created_at — status pertama yang dipakai.
        Schema::table('provider_profiles', function (Blueprint $table): void {
            $table->index(['verification_status', 'created_at'], 'provider_profiles_verification_index');
            $table->index('organization_name', 'provider_profiles_organization_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_profiles');
    }
};
