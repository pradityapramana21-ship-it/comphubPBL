<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Core\View;
use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\AuditLogRepository;
use App\Repositories\Contracts\ProviderProfileRepository;
use App\Repositories\Contracts\UserRepository;

final class AdminUserController extends Controller
{
    public function __construct(
        View $view,
        private readonly UserRepository $users,
        private readonly ProviderProfileRepository $providers,
        private readonly Database $database,
        private readonly AuditLogRepository $audit,
        private readonly Auth $auth,
    ) {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        $filters = [
            'keyword' => $request->string('q'),
            'role' => $request->string('role'),
            'status' => $request->string('status'),
            'page' => max(1, (int) $request->input('page', 1)),
        ];

        return $this->renderPage('admin/users/index', [
            'title' => 'Pengguna',
            'users' => $this->users->paginateForAdmin($filters, 20),
            'filters' => $filters,
            'roles' => UserRole::values(),
            'statuses' => AccountStatus::values(),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->renderPage('admin/users/form', [
            'title' => 'Tambah pengguna',
            'user' => null,
            'roles' => UserRole::values(),
            'statuses' => AccountStatus::values(),
        ]);
    }

    public function store(Request $request): Response
    {
        $data = $this->validateUser($request, null);
        $role = UserRole::from((string) $data['role']);

        $user = $this->database->transaction(function () use ($data, $role): array {
            $user = $this->users->create(
                (string) $data['name'],
                (string) $data['email'],
                password_hash((string) $data['password'], PASSWORD_DEFAULT),
                $role,
            );

            if ($role === UserRole::Provider) {
                $this->createProviderProfile($user);
            }

            if (($data['status'] ?? 'active') === 'suspended') {
                $this->users->updateStatus((int) $user['id'], AccountStatus::Suspended, 'Dinonaktifkan administrator.');
            }

            return $user;
        });

        $this->audit($request, 'user.create', (int) $user['id'], ['role' => $role->value]);
        app()->session()->flash('status', 'Pengguna dibuat.');

        return $this->redirect(url('/admin/pengguna'));
    }

    public function edit(Request $request): Response
    {
        $user = $this->findUser($request);

        return $this->renderPage('admin/users/form', [
            'title' => 'Ubah pengguna',
            'user' => $user,
            'roles' => UserRole::values(),
            'statuses' => AccountStatus::values(),
        ]);
    }

    public function update(Request $request): Response
    {
        $user = $this->findUser($request);
        $data = $this->validateUser($request, $user);
        $role = UserRole::from((string) $data['role']);
        $status = AccountStatus::from((string) $data['status']);
        $self = (int) $user['id'] === (int) $this->auth->id();

        if ($self && ($role !== UserRole::Admin || $status !== AccountStatus::Active)) {
            throw new ValidationException(
                ['umum' => ['Anda tidak dapat menurunkan atau menonaktifkan akun sendiri.']],
                $this->oldInput($request),
            );
        }

        $oldRole = (string) $user['role'];

        $this->database->transaction(function () use ($user, $data, $role, $status, $oldRole): void {
            $this->users->updateProfile((int) $user['id'], [
                'name' => (string) $data['name'],
                'email' => (string) $data['email'],
            ]);
            $this->users->updateRole((int) $user['id'], $role);
            $this->users->updateStatus(
                (int) $user['id'],
                $status,
                $status === AccountStatus::Suspended ? 'Dinonaktifkan administrator.' : null,
            );

            $profile = $this->providers->findByUserId((int) $user['id']);

            if ($role === UserRole::Provider && $profile === null) {
                $this->createProviderProfile($this->users->findById((int) $user['id']) ?? $user);
            }

            if ($role !== UserRole::Provider && $profile !== null) {
                $this->providers->update((int) $profile['id'], ['verification_status' => 'pending']);
            }
        });

        $this->audit($request, 'user.update', (int) $user['id'], [
            'role' => $role->value,
            'status' => $status->value,
            'old_role' => $oldRole,
        ]);
        app()->session()->flash('status', 'Pengguna diperbarui.');

        return $this->redirect(url('/admin/pengguna'));
    }

    public function destroy(Request $request): Response
    {
        $user = $this->findUser($request);

        if ((int) $user['id'] === (int) $this->auth->id()) {
            return $this->fail('Anda tidak dapat menghapus akun sendiri.');
        }

        if ((string) $user['role'] === UserRole::Admin->value
            && (int) ($this->users->countByRole()['admin'] ?? 0) <= 1
        ) {
            return $this->fail('Admin terakhir tidak dapat dihapus.');
        }

        $this->audit($request, 'user.delete', (int) $user['id'], ['email' => $user['email']]);
        $this->users->delete((int) $user['id']);
        app()->session()->flash('status', 'Pengguna dihapus.');

        return $this->redirect(url('/admin/pengguna'));
    }

    /** @return array<string, mixed> */
    private function findUser(Request $request): array
    {
        $id = (int) (($request->attribute('route', []))['user'] ?? 0);
        $user = $this->users->findById($id);

        if ($user === null) {
            $this->abort(404);
        }

        return $user;
    }

    /** @return array<string, mixed> */
    private function validateUser(Request $request, ?array $user): array
    {
        $id = $user === null ? null : (int) $user['id'];
        $rules = [
            'name' => 'required|string|min:2|max:255',
            'email' => 'required|email|max:190|unique:users,email' . ($id === null ? '' : ',' . $id),
            'role' => 'required|in:' . implode(',', UserRole::values()),
            'status' => 'required|in:' . implode(',', AccountStatus::values()),
        ];

        if ($user === null) {
            $rules['password'] = 'required|string|min:8|max:255|confirmed';
        }

        try {
            return (new Validator($request->all(), [], $rules, [], [
                'name' => 'nama',
                'email' => 'email',
                'role' => 'peran',
                'status' => 'status',
                'password' => 'kata sandi',
            ]))->validate();
        } catch (ValidationException $exception) {
            throw new ValidationException($exception->errors(), $this->oldInput($request));
        }
    }

    /** @return array<string, mixed> */
    private function oldInput(Request $request): array
    {
        $old = $request->all();
        unset($old['_token'], $old['password'], $old['password_confirmation']);

        return $old;
    }

    /** @param array<string, mixed> $user */
    private function createProviderProfile(array $user): void
    {
        $this->providers->create([
            'user_id' => (int) $user['id'],
            'organization_name' => (string) $user['name'],
            'campus_name' => 'Belum diisi',
            'official_email' => (string) $user['email'],
            'description' => 'Profil dibuat administrator. Lengkapi data organisasi.',
        ]);
    }

    /** @param array<string, mixed> $metadata */
    private function audit(Request $request, string $action, int $targetId, array $metadata): void
    {
        $this->audit->record(
            $this->auth->id(),
            UserRole::Admin->value,
            $action,
            'user',
            $targetId,
            $metadata,
            $request->ip(),
            $request->header('user-agent'),
        );
    }

    private function fail(string $message): Response
    {
        app()->session()->flash('_errors', ['umum' => [$message]]);

        return $this->redirect(url('/admin/pengguna'));
    }
}
