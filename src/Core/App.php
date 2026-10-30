<?php

declare(strict_types=1);

namespace App\Core;

use App\Http\Middleware\MiddlewareRegistry;

final class App
{
    public function __construct(private readonly Container $container)
    {
        $GLOBALS['app'] = $this;
    }

    public function handle(Request $request): Response
    {
        $this->session()->start();
        $this->session()->ageFlash();
        $this->auth()->bootFromRememberCookie();

        if (! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)
            && ! $this->csrf()->verify(is_string($request->input('_token')) ? $request->input('_token') : null)
        ) {
            throw new HttpException(419, 'Sesi formulir kedaluwarsa. Muat ulang halaman.');
        }

        $response = $this->router()->dispatch($request);

        return $this->applySecurityHeaders($response);
    }

    public function invoke(Request $request): Response
    {
        $this->session()->start();
        $this->session()->ageFlash();

        return $this->router()->dispatch($request);
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function router(): Router
    {
        return $this->container->make(Router::class);
    }

    public function session(): Session
    {
        return $this->container->make(Session::class);
    }

    public function auth(): Auth
    {
        return $this->container->make(Auth::class);
    }

    public function csrf(): Csrf
    {
        return $this->container->make(Csrf::class);
    }

    public function view(): View
    {
        return $this->container->make(View::class);
    }

    public function renderError(int $status, ?string $message = null): Response
    {
        $errors = [
            403 => ['Forbidden', 'Anda tidak berhak mengakses halaman ini.'],
            404 => ['Tidak Ditemukan', 'Halaman yang Anda cari tidak tersedia.'],
            405 => ['Metode Tidak Didukung', 'Permintaan ini tidak dapat diproses.'],
            419 => ['Sesi Kedaluwarsa', 'Muat ulang halaman lalu coba lagi.'],
            429 => ['Terlalu Banyak Permintaan', 'Tunggu sebentar lalu coba lagi.'],
            500 => ['Kesalahan Server', 'Terjadi kesalahan. Coba lagi nanti.'],
        ];

        [$title, $default] = $errors[$status] ?? ['Kesalahan', 'Terjadi kesalahan.'];

        try {
            $html = $this->view()->render('errors/error', [
                'title' => $title,
                'status' => $status,
                'message' => $message ?? $default,
            ]);
        } catch (\Throwable) {
            $html = '<!doctype html><html lang="id"><meta charset="utf-8"><title>'
                . e($title) . '</title><h1>' . $status . ' — ' . e($title) . '</h1><p>'
                . e($message ?? $default) . '</p>';
        }

        return Response::html($html, $status);
    }

    public static function html(string $body, int $status = 200): Response
    {
        return Response::html($body, $status);
    }

    public static function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    public static function redirect(string $path, int $status = 302): Response
    {
        return Response::redirect($path, $status);
    }

    public static function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }

    private function applySecurityHeaders(Response $response): Response
    {
        return $response
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader(
                'Content-Security-Policy',
                "default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; "
                . "form-action 'self'; frame-ancestors 'none'; base-uri 'self'",
            )
            ->withHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }
}
