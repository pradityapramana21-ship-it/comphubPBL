<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\EventRepository;
use App\Repositories\Contracts\ProviderProfileRepository;

final class ProviderDirectoryController extends Controller
{
    public function __construct(
        View $view,
        private readonly ProviderProfileRepository $providers,
        private readonly EventRepository $events,
    ) {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        $keyword = trim((string) $request->input('q', ''));
        $keyword = mb_substr($keyword, 0, 100);

        return $this->renderPage('providers/index', [
            'title' => 'Penyelenggara',
            'description' => 'Direktori organisasi kampus yang sudah terverifikasi.',
            'keyword' => $keyword,
            'providers' => $this->providers->paginatePublicDirectory($keyword !== '' ? $keyword : null),
        ]);
    }

    public function show(Request $request): Response
    {
        $route = (array) $request->attribute('route', []);
        $profile = $this->providers->findVerifiedBySlug((string) ($route['slug'] ?? ''));

        if ($profile === null) {
            throw HttpException::notFound();
        }

        return $this->renderPage('providers/show', [
            'title' => (string) $profile['organization_name'],
            'description' => 'Profil penyelenggara ' . $profile['organization_name'] . '.',
            'profile' => $profile,
            'events' => $this->events->findApprovedByProvider((int) $profile['id']),
        ]);
    }
}
