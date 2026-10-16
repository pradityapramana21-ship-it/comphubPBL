<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\VerificationStatus;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Repositories\Contracts\ModerationLogRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Keputusan admin atas verifikasi organisasi.
 *
 * Penolakan WAJIB punya alasan. Tanpa alasan, penyedia tidak mungkin
 * memperbaiki pengajuannya dan akan mengajukan ulang tanpa perubahan —
 * yang hanya membebani antrean admin.
 */
class ProviderVerificationService
{
    public function __construct(
        private readonly AuditLogService $audit,
        private readonly ModerationLogRepository $moderationLogs,
    ) {}

    public function verify(ProviderProfile $provider, User $admin): ProviderProfile
    {
        return DB::transaction(function () use ($provider, $admin): ProviderProfile {
            if ($provider->verification_status === VerificationStatus::Verified) {
                throw ValidationException::withMessages([
                    'provider' => 'Organisasi ini sudah terverifikasi.',
                ]);
            }

            $provider->markVerified($admin);

            $this->audit->providerVerified($admin, $provider);

            return $provider->refresh();
        });
    }

    /**
     * @param  string  $reason  wajib, minimal beberapa karakter
     */
    public function reject(ProviderProfile $provider, User $admin, string $reason): ProviderProfile
    {
        return DB::transaction(function () use ($provider, $admin, $reason): ProviderProfile {
            if ($provider->verification_status === VerificationStatus::Rejected) {
                throw ValidationException::withMessages([
                    'provider' => 'Organisasi ini sudah ditolak.',
                ]);
            }

            $provider->markRejected($admin, $reason);

            $this->audit->providerRejected($admin, $provider, $reason);

            return $provider->refresh();
        });
    }

    /**
     * Meminta penyedia mengajukan ulang, atau mengembalikan ke antrean.
     */
    public function reopen(ProviderProfile $provider, User $admin, ?string $note = null): ProviderProfile
    {
        $provider->markPending($admin, $note);

        $this->audit->record('provider.reopened', $provider, array_filter(['catatan' => $note]));

        return $provider->refresh();
    }

    /**
     * Penyedia memperbarui dokumen bukti lalu mengajukan ulang.
     */
    public function resubmit(ProviderProfile $provider, ?string $documentPath, ?string $note): ProviderProfile
    {
        $provider->forceFill(array_filter([
            'verification_document_path' => $documentPath,
            'verification_note' => $note,
        ]))->save();

        $provider->markPending($provider->user, $note);

        $this->audit->record('provider.resubmitted', $provider);

        return $provider->refresh();
    }
}
