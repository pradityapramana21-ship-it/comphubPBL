<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\PasswordResetService;

final class PasswordResetController extends Controller
{
    public function __construct(
        View $view,
        private readonly PasswordResetService $resetService,
    ) {
        parent::__construct($view);
    }

    public function showRequest(): Response
    {
        return $this->renderPage('auth/forgot-password', ['title' => 'Lupa Kata Sandi']);
    }

    public function send(Request $request): Response
    {
        $data = (new ForgotPasswordRequest($request))->validate();
        $this->resetService->request((string) $data['email']);

        app()->session()->flash('status', 'Jika email terdaftar, tautan atur ulang telah dikirim.');

        return $this->redirect(url('/lupa-sandi'));
    }

    public function showReset(Request $request): Response
    {
        $route = (array) $request->attribute('route', []);

        return $this->renderPage('auth/reset-password', [
            'title' => 'Atur Ulang Kata Sandi',
            'token' => (string) ($route['token'] ?? ''),
            'email' => (string) $request->input('email', ''),
        ]);
    }

    public function update(Request $request): Response
    {
        $data = (new ResetPasswordRequest($request))->validate();
        $this->resetService->reset(
            (string) $data['email'],
            (string) $data['token'],
            (string) $data['password'],
        );

        app()->session()->flash('status', 'Kata sandi berhasil diperbarui. Silakan masuk.');

        return $this->redirect(url('/masuk'));
    }
}
