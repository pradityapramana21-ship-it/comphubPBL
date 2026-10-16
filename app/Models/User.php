<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'password', 'role', 'account_status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Default di level model, bukan hanya di level database.
     *
     * Tanpa ini, instance yang baru dibuat lewat User::create() punya
     * account_status = null sampai di-refresh. Middleware EnsureAccountIsActive
     * memperlakukan null sebagai "bukan aktif", jadi user yang sah langsung
     * ikut tertolak. Category::is_active punya masalah yang sama dan sudah
     * ditangani dengan cara yang sama.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => 'user',
        'account_status' => 'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'account_status' => AccountStatus::class,
            'suspended_at' => 'datetime',
        ];
    }

    // ------------------------------------------------------------ relasi

    public function providerProfile(): HasOne
    {
        return $this->hasOne(ProviderProfile::class);
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class);
    }

    /**
     * Kegiatan yang disimpan pengguna — sumber halaman "Tersimpan".
     *
     * Sama seperti Event::bookmarkedBy(), relasi ini untuk MEMBACA saja.
     * Penulisan lewat BookmarkRepository, bukan attach(), supaya tidak
     * ada kolom `updated_at` yang trying ditulis ke tabel `bookmarks`.
     */
    public function bookmarkedEvents(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'bookmarks')
            ->withPivot('created_at')
            ->orderByPivot('created_at', 'desc');
    }

    /** Laporan yang dibuat pengguna. Identitasnya TIDAK boleh tampil ke penyedia. */
    public function reports(): HasMany
    {
        return $this->hasMany(EventReport::class, 'reporter_user_id');
    }

    public function moderationLogs(): HasMany
    {
        return $this->hasMany(ModerationLog::class, 'actor_user_id');
    }

    // -------------------------------------------------------------- scope

    public function scopeWithRole(Builder $query, UserRole $role): Builder
    {
        return $query->where('role', $role->value);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('account_status', AccountStatus::Active->value);
    }

    public function scopeExceptAdmins(Builder $query): Builder
    {
        return $query->where('role', '!=', UserRole::Admin->value);
    }

    // ----------------------------------------------------------- helper

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isProvider(): bool
    {
        return $this->role === UserRole::Provider;
    }

    /**
     * Role belum tentu berarti akses penuh: admin yang ditangguhkan tetap
     * tidak boleh masuk, dan penyedia belum verifikasi belum boleh mengajukan
     * kegiatan. Kedua pemeriksaan ini terpisah dengan sengaja (Panduan §2).
     */
    public function canAccessApplication(): bool
    {
        return $this->account_status->canLogin();
    }

    public function hasVerifiedProvider(): bool
    {
        return $this->providerProfile?->verification_status?->canSubmitEvents() ?? false;
    }

    /**
     * Inisial untuk avatar teks, dipakai kalau pengguna tidak mengunggah gambar.
     */
    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $letters = array_map(
            static fn (string $part): string => Str::upper(Str::substr($part, 0, 1)),
            array_slice(array_filter($parts), 0, 2),
        );

        return $letters === [] ? '?' : implode('', $letters);
    }
}
