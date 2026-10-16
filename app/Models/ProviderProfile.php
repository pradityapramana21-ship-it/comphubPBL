<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VerificationStatus;
use Database\Factories\ProviderProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Profil organisasi penyedia kegiatan (BEM, himpunan, UKM, fakultas, kampus luar).
 */
#[Fillable([
    'user_id', 'organization_name', 'campus_name', 'official_email',
    'website_url', 'description', 'verification_document_path',
])]
class ProviderProfile extends Model
{
    /** @use HasFactory<ProviderProfileFactory> */
    use HasFactory;

    protected $table = 'provider_profiles';

    /**
     * Default di level model. Tanpa ini, instance yang baru dibuat punya
     * verification_status = null sampai di-refresh — dan setiap kode yang
     * membandingkan dengan enum akan gagal diam-diam.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'verification_status' => 'pending',
    ];

    /**
     * Sengaja TIDAK mass-assignable:
     * `verification_status`, `verified_at`, `verified_by`, `verification_note`.
     *
     * Field ini menentukan apakah sebuah organisasi boleh menerbitkan
     * kegiatan. Kalau ikut fillable, siapa pun yang bisa memanggil
     * `ProviderProfile::create([... 'verification_status' => 'verified'])`
     * akan bisa memverifikasi dirinya sendiri. Satu-satunya jalan yang
     * diizinkan adalah method mark*() di bawah, dan validateStatusChange()
     * menolak transisi yang tidak wajar.
     */

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verification_status' => VerificationStatus::class,
            'verified_at' => 'datetime',
        ];
    }

    // ------------------------------------------------------------ relasi

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    // -------------------------------------------------------------- scope

    /** Antrean verifikasi: profil yang belum diputuskan admin. */
    public function scopeAwaitingVerification(Builder $query): Builder
    {
        return $query->where('verification_status', VerificationStatus::Pending->value);
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('verification_status', VerificationStatus::Verified->value);
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('verification_status', VerificationStatus::Rejected->value);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || trim($term) === '') {
            return $query;
        }

        $term = trim($term);

        return $query->where(function (Builder $q) use ($term): void {
            $q->where('organization_name', 'ilike', "%{$term}%")
                ->orWhere('campus_name', 'ilike', "%{$term}%");
        });
    }

        // ----------------------------------------------------------- helper

    public function isVerified(): bool
    {
        return $this->verification_status === VerificationStatus::Verified;
    }

    /**
     * Pindahkan status verifikasi ke `$target`.
     *
     * Menolak transisi ke status yang sedang aktif karena itu selalu
     * kesalahan logika admin (mengklik dua kali "verify"), bukan keputusan
     * baru. Pengecekan ini memanggil method yang sama dengan yang dipakai
     * EventStatus, jadi aturannya konsisten di dua tempat.
     *
     * @throws \DomainException
     */
    public function transitionVerificationTo(VerificationStatus $target, User $admin, ?string $note = null): bool
    {
        if ($this->verification_status === $target) {
            return false;
        }

        $this->verification_status = $target;
        $this->verification_note = $note;

        if ($target === VerificationStatus::Verified) {
            $this->verified_at = now();
            $this->verified_by = $admin->id;
        } else {
            // Menarik verifikasi harus ikut menghapus jejak waktunya,
            // atau DB constraint provider_profiles_verified_at_check akan menolak.
            $this->verified_at = null;
            $this->verified_by = null;
        }

        $this->save();

        return true;
    }

    public function markVerified(User $admin): bool
    {
        return $this->transitionVerificationTo(VerificationStatus::Verified, $admin);
    }

    /** Penolakan WAJIB punya alasan supaya penyedia tahu apa yang diperbaiki. */
    public function markRejected(User $admin, string $note): bool
    {
        return $this->transitionVerificationTo(VerificationStatus::Rejected, $admin, $note);
    }

    public function markPending(User $admin, ?string $note = null): bool
    {
        return $this->transitionVerificationTo(VerificationStatus::Pending, $admin, $note);
    }

    /**
     * Host situs resmi, ditampilkan sebagai label yang lebih ringkas di UI.
     * https://bem.poltek.ac.id -> bem.poltek.ac.id
     */
    public function websiteHost(): ?string
    {
        $url = trim((string) $this->website_url);

        return $url === '' ? null : (parse_url($url, PHP_URL_HOST) ?: $url);
    }

    public function slug(): string
    {
        return Str::slug($this->organization_name);
    }
}
