<?php

declare(strict_types=1);

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom domain pada tabel `users`.
 *
 * Migration terpisah, bukan menyunting 0001_01_01_000000_create_users_table,
 * karena Panduan §5.1 melarang mengubah migration yang sudah dijalankan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 20)
                ->default(UserRole::User->value)
                ->after('password')
                ->comment('user | provider | admin');

            $table->string('account_status', 20)
                ->default(AccountStatus::Active->value)
                ->after('role')
                ->comment('active | suspended');

            $table->timestampTz('suspended_at')->nullable()->after('account_status');
            $table->text('suspension_note')->nullable()->after('suspended_at');
        });

        // Integritas ditegakkan database, bukan hanya validasi form
        // (Panduan §6.4).
        DB::statement(
            "ALTER TABLE users ADD CONSTRAINT users_role_check
             CHECK (role IN ('user', 'provider', 'admin'))"
        );
        DB::statement(
            "ALTER TABLE users ADD CONSTRAINT users_account_status_check
             CHECK (account_status IN ('active', 'suspended'))"
        );
        // Akun yang ditangguhkan wajib punya jejak penangguhan agar
        // admin bisa menjelaskan alasan Freeze ke pemohon.
        DB::statement(
            "ALTER TABLE users ADD CONSTRAINT users_suspension_check
             CHECK (account_status <> 'suspended' OR suspended_at IS NOT NULL)"
        );

        // Query nyata: middleware memfilter akun yang bukan admin.
        Schema::table('users', function (Blueprint $table): void {
            $table->index(['role', 'account_status'], 'users_role_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_role_status_index');
            $table->dropColumn(['role', 'account_status', 'suspended_at', 'suspension_note']);
        });
    }
};
