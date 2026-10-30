<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\Contracts\EventRepository;
use App\Services\UploadService;

final class MediaController
{
    public function __construct(
        private readonly EventRepository $events,
        private readonly UploadService $uploads,
    ) {
    }

    public function poster(Request $request): Response
    {
        return $this->serve($request, 'poster');
    }

    public function guidebook(Request $request): Response
    {
        return $this->serve($request, 'guidebook');
    }

    private function serve(Request $request, string $kind): Response
    {
        $route = (array) $request->attribute('route', []);
        $event = $this->events->findById((int) ($route['event'] ?? 0));
        $path = (string) ($event[$kind . '_path'] ?? '');
        $user = auth_user();
        $admin = is_array($user) && ($user['role'] ?? '') === 'admin';

        if ($event === null || $path === '') {
            throw HttpException::notFound();
        }

        if (! $admin && (($event['status'] ?? '') !== 'approved' || ($event['archived_at'] ?? null) !== null)) {
            throw HttpException::notFound();
        }

        $absolute = $this->uploads->absolutePath($path);

        if ($absolute === null || ! is_file($absolute)) {
            throw HttpException::notFound();
        }

        $mime = $this->uploads->mime($absolute);
        $filename = $kind === 'guidebook' ? 'panduan-' . $event['slug'] . '.pdf' : 'poster-' . $event['slug'];

        return new Response((string) file_get_contents($absolute), 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) filesize($absolute),
            'Content-Disposition' => 'inline; filename="' . str_replace('"', '', $filename) . '"',
            'Cache-Control' => 'private, max-age=3600',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
