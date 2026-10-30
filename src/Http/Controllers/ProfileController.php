<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Http\Requests\Profile\ChangePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Repositories\Contracts\UserRepository;
use App\Services\AuthService;

final class ProfileController extends Controller
{
    public function __construct(
        View $view,
        private readonly UserRepository $users,
        private readonly Auth $auth,
        private readonly AuthService $authService,
    ) {
        parent::__construct($view);
    }

    public function edit(Request $request): Response
    {
        return $this->renderPage('user/profile', [
            'title' => 'Profil',
            'user' => $this->auth->user(),
        ]);
    }

    public function update(Request $request): Response
    {
        $data = (new UpdateProfileRequest($request))->validate();

        $this->users->updateProfile((int) $this->auth->id(), [
            'name' => $data['name'],
            'email' => $data['email'],
        ]);
        $this->auth->refresh();
        app()->session()->flash('status', 'Profil diperbarui.');

        return $this->redirect(url('/profil'));
    }

    public function updatePassword(Request $request): Response
    {
        $data = (new ChangePasswordRequest($request))->validate();

        $this->authService->changePassword(
            (int) $this->auth->id(),
            (string) $data['current_password'],
            (string) $data['password'],
        );
        app()->session()->flash('status', 'Kata sandi diperbarui.');

        return $this->redirect(url('/profil'));
    }
}
