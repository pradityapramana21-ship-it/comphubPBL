<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProviderDecisionRequest;
use App\Models\ProviderProfile;
use App\Repositories\Contracts\ProviderProfileRepository;
use App\Services\ProviderVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProviderVerificationController extends Controller
{
    public function index(Request $request, ProviderProfileRepository $providers): View
    {
        return view('admin.providers.index', [
            'queue' => $providers->paginateVerificationQueue(perPage: 15),
            'all' => $providers->paginatePublicDirectory(
                keyword: $request->query('q'),
                perPage: 15,
            ),
            'keyword' => trim((string) $request->query('q', '')),
            'perStatus' => $providers->countByVerificationStatus(),
        ]);
    }

    /**
     * Satu endpoint untuk tiga keputusan.
     *
     * Penolakan WAJIB menyertakan alasan. Verifikasi tanpa penjelasan
     * hanya memberi tahu organisasi itu gagal, tanpa memberi jalan untuk
     * memperbaikinya.
     */
    public function decide(
        ProviderDecisionRequest $request,
        ProviderProfile $provider,
        ProviderVerificationService $verification,
    ): RedirectResponse {
        match ($request->decision()) {
            'verify' => $verification->verify($provider, $request->user()),
            'reject' => $verification->reject($provider, $request->user(), (string) $request->reasonText()),
            default => $verification->reopen($provider, $request->user(), $request->reasonText()),
        };

        $this->flashSuccess(match ($request->decision()) {
            'verify' => 'Organisasi terverifikasi. Sekarang bisa mengajukan kegiatan.',
            'reject' => 'Verifikasi ditolak. Organisasi menerima alasannya.',
            default => 'Pengajuan dikembalikan ke antrean verifikasi.',
        });

        return back();
    }
}
