<?php

declare(strict_types=1);

namespace App\Http\Controllers\Provider;

use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProviderProfileRequest;
use App\Repositories\Contracts\ProviderProfileRepository;
use App\Services\ProviderVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Profil organisasi dan pengajuan verifikasi.
 *
 * Perhatikan apa yang TIDAK bisa dilakukan di sini: mengubah status
 * verifikasi sendiri. Kolom itu tidak mass-assignable dan hanya bisa
 * diubah lewat ProviderProfile::markVerified()/markRejected(), yang
 * mencatat pelaku keputusannya (Panduan §4.5).
 */
class ProviderProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $profile = $this->profileFor($request->user());

        abort_if($profile === null, 403);

        return view('provider.profile.edit', [
            'profile' => $profile,
            'canResubmit' => $profile->verification_status === VerificationStatus::Rejected,
        ]);
    }

    public function update(
        UpdateProviderProfileRequest $request,
        ProviderProfileRepository $providers,
        ProviderVerificationService $verification,
    ): RedirectResponse {
        $profile = $this->profileFor($request->user());

        abort_if($profile === null, 403);

        $attributes = $request->profileAttributes();

        if ($request->hasFile('verification_document')) {
            // Berkas lama dibuang lebih dulu supaya folder upload tidak
            // menumpuk dokumen yang sudah tidak berlaku.
            if ($profile->verification_document_path !== null) {
                \Illuminate\Support\Facades\Storage::disk('local')
                    ->delete($profile->verification_document_path);
            }

            $attributes['verification_document_path'] = $request->file('verification_document')
                ->store('uploads/verification', 'local');
        }

        $providers->update($profile->id, $attributes);

        if ($request->boolean('resubmit')) {
            $verification->resubmit(
                $profile->fresh(),
                $attributes['verification_document_path'] ?? $profile->verification_document_path,
                $request->input('verification_note'),
            );

            $this->flashSuccess('Pengajuan verifikasi dikirim ulang. Administrator akan memeriksa kembali.');

            return redirect()->route('provider.profile.edit');
        }

        $this->flashSuccess('Data organisasi tersimpan.');

        return redirect()->route('provider.profile.edit');
    }
}
