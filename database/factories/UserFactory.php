<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 *
 * Password semua akun hasil factory ini sama: "password".
 * Wajib untuk development dan test, dan tidak boleh dipakai di mana pun
 * yang menghadap pengguna sungguhan.
 *
 * Tiga kolom sengaja tidak dipakai lewat fillable: `email_verified_at`,
 * `suspended_at`, dan `suspension_note`.
 * Nilainya dipasang lewat forceFill() supaya benar-benar tersimpan —
 * kalau hanya mengandalkan fillable, nilainya diabaikan diam-diam dan
 * test lanjutan bisa salah tanpa pesan error.
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    private static ?string $password = null;

    /**
     * Setiap akun hasil factory dianggap sudah verifikasi email.
     *
     * Pendaftaran di aplikasi ini datang dari luar, jadi data
     * pengembangan tidak perlu meniru keadaan "belum verifikasi".
     *
     * Ditetapkan lewat newModel(), bukan afterMaking() di constructor:
     * afterMaking() mengembalikan instance BARU, jadi pemanggilannya di
     * constructor akan mengulang dirinya sendiri sampai proses PHP
     * berhenti mendadak. newModel() dipanggil sekali per pembuatan
     * model, dan state `unverified()` tetap menimpanya setelahnya.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function newModel(array $attributes = [])
    {
        return parent::newModel($attributes)->forceFill(['email_verified_at' => now()]);
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => $this->uniqueEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::User->value,
            'account_status' => AccountStatus::Active->value,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Email yang dijamin unik.
     *
     * Sengaja tidak memakai fake()->unique()->safeEmail().
     * UniqueGenerator menyimpan daftarnya di dalam objek Faker itu
     * sendiri, dan di lingkungan ini setiap factory baru mendapat urutan
     * nilai yang sama — unique() lalu kehabisan percobaan dan melempar
     * OverflowException. Suffiks acak tidak pernah mengulang.
     */
    private function uniqueEmail(): string
    {
        return Str::lower(fake()->firstName())
            .'.'.Str::lower(fake()->lastName())
            .'.'.Str::lower(Str::random(8))
            .'@example.test';
    }

    public function unverified(): static
    {
        return $this->afterMaking(function (User $user): void {
            $user->forceFill(['email_verified_at' => null]);
        });
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => UserRole::Admin->value,
        ]);
    }

    public function provider(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => UserRole::Provider->value,
        ]);
    }

    /**
     * Akun yang ditangguhkan, lengkap dengan waktu dan alasannya.
     *
     * Dipakai supaya halaman "pengguna" milik admin punya sesuatu untuk
     * ditampilkan besides akun yang semuanya beres.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes): array => [
            'account_status' => AccountStatus::Suspended->value,
        ])->afterMaking(function (User $user): void {
            $user->forceFill([
                'suspended_at' => now(),
                'suspension_note' => 'Melanggar ketentuan penggunaan layanan.',
            ]);
        });
    }
}