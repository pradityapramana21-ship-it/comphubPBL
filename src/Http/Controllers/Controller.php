<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

abstract class Controller
{
    public function __construct(protected readonly View $view)
    {
    }

    /** @param array<string, mixed> $data */
    protected function render(string $template, array $data = [], int $status = 200): Response
    {
        return Response::html($this->view->render($template, $data), $status);
    }

    /** @param array<string, mixed> $data */
    protected function renderPage(string $template, array $data = [], int $status = 200): Response
    {
        $user = auth_user();
        $notificationCount = 0;

        if ($user !== null) {
            $since = (string) ($user['events_seen_at'] ?? 'now');
            $notificationCount = app()->container()
                ->make(\App\Repositories\Contracts\EventRepository::class)
                ->countNewSince($since);
        }

        $data += ['currentUser' => $user, 'notificationCount' => $notificationCount];
        $content = $this->view->render($template, $data);

        return Response::html($this->view->render('layouts/app', $data + ['content' => $content]), $status);
    }

    protected function redirect(string $to, int $status = 302): Response
    {
        return Response::redirect($to, $status);
    }

    protected function back(Request $request, string $fallback = '/'): Response
    {
        $referer = $request->header('referer');

        if (is_string($referer) && str_starts_with($referer, '/') && ! str_starts_with($referer, '//')) {
            return $this->redirect($referer);
        }

        return $this->redirect($fallback);
    }

    protected function abort(int $status, string $message = ''): never
    {
        throw new HttpException($status, $message);
    }
}
