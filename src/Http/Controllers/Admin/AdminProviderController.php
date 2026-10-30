<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Core\View;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\AuditLogRepository;
use App\Repositories\Contracts\ProviderProfileRepository;

final class AdminProviderController extends Controller
{
    public function __construct(
        View $view,
        private readonly ProviderProfileRepository $providers,
        private readonly AuditLogRepository $audit,
        private readonly Auth $auth,
    ) {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        $status = $request->string('status');
        $keyword = trim($request->string('q'));

        return $this->renderPage('admin/providers/index', [
            'title' => 'Penyelenggara',
            'providers' => $this->providers->paginateForAdmin(
                $keyword,
                in_array($status, VerificationStatus::values(), true) ? $status : '',
                20,
                max(1, (int) $request->input('page', 1)),
            ),
            'filters' => ['q' => $keyword, 'status' => $status],
            'statuses' => VerificationStatus::values(),
        ]);
    }

    public function edit(Request $request): Response
    {
        return $this->renderPage('admin/providers/form', [
            'title' => 'Ubah penyelenggara',
            'provider' => $this->findProvider($request),
            'statuses' => VerificationStatus::values(),
        ]);
    }

    public function update(Request $request): Response
    {
        $provider = $this->findProvider($request);
        $data = (new Validator($request->all(), [], [
            'organization_name' => 'required|string|min:2|max:150',
            'campus_name' => 'required|string|min:2|max:150',
            'official_email' => 'required|email|max:190',
            'website_url' => 'nullable|url|max:255',
            'description' => 'nullable|string|max:3000',
            'verification_status' => 'required|in:' . implode(',', VerificationStatus::values()),
            'verification_note' => 'nullable|string|max:2000',
        ], [], [
            'organization_name' => 'nama organisasi',
            'campus_name' => 'nama kampus',
            'official_email' => 'email resmi',
            'website_url' => 'situs web',
            'verification_status' => 'status verifikasi',
        ]))->validate();

        $status = VerificationStatus::from((string) $data['verification_status']);
        $attributes = [
            'organization_name' => trim((string) $data['organization_name']),
            'campus_name' => trim((string) $data['campus_name']),
            'official_email' => trim((string) $data['official_email']),
            'website_url' => $data['website_url'] ?? null,
            'description' => $data['description'] ?? null,
            'verification_status' => $status->value,
            'verification_note' => $data['verification_note'] ?? null,
        ];

        if ($status === VerificationStatus::Verified) {
            $attributes['verified_by'] = $this->auth->id();
        }

        $this->providers->update((int) $provider['id'], $attributes);
        $this->audit->record(
            $this->auth->id(),
            UserRole::Admin->value,
            'provider.update',
            'provider',
            (int) $provider['id'],
            ['verification_status' => $status->value],
            $request->ip(),
            $request->header('user-agent'),
        );

        app()->session()->flash('status', 'Penyelenggara diperbarui.');

        return $this->redirect(url('/admin/penyedia'));
    }

    /** @return array<string, mixed> */
    private function findProvider(Request $request): array
    {
        $id = (int) (($request->attribute('route', []))['provider'] ?? 0);
        $provider = $this->providers->findById($id);

        if ($provider === null) {
            $this->abort(404);
        }

        return $provider;
    }
}
