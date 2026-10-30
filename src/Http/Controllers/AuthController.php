<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\AuthService;

final class AuthController extends Controller
{
    public function __construct(
        View $view,
        private readonly AuthService $authService,
        private readonly Auth $auth,
    ) {
        parent::__construct($view);
    }

    public function showLogin(Request $request): Response
    {
        return $this->renderPage('auth/login', [
            'title' => 'Masuk',
            'next' => $this->safeNext($request->input('next')),
        ]);
    }

    public function login(Request $request): Response
    {
        $data = (new LoginRequest($request))->validate();
        $this->authService->login(
            (string) $data['email'],
            (string) $data['password'],
            (bool) ($data['remember'] ?? false),
            $request->ip(),
        );

        return $this->redirect($this->destination($this->safeNext($data['next'] ?? null)));
    }

    public function showRegister(Request $request): Response
    {
        return $this->renderPage('auth/register', [
            'title' => 'Daftar Akun',
            'next' => $this->safeNext($request->input('next')),
        ]);
    }

    public function register(Request $request): Response
    {
        $data = (new RegisterRequest($request))->validate();
        $user = $this->authService->registerUser($data);
        $this->auth->login($user, false);

        return $this->redirect($this->destination($this->safeNext($data['next'] ?? null)));
    }

    public function logout(Request $request): Response
    {
        $this->authService->logout();

        return $this->redirect(url('/'));
    }

    private function destination(string $next): string
    {
        if ($next !== '') {
            return url($next);
        }

        return url('/dasbor');
    }

    private function safeNext(mixed $next): string
    {
        if (! is_string($next) || $next === '') {
            return '';
        }

        if (! str_starts_with($next, '/') || str_starts_with($next, '//') || str_contains($next, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $next) === 1
        ) {
            return '';
        }

        return $next;
    }
}
