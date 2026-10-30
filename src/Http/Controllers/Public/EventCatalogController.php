<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\DataTransferObjects\CatalogFilter;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\BookmarkRepository;
use App\Repositories\Contracts\CategoryRepository;
use App\Repositories\Contracts\EventRepository;
use App\Support\UrlHelper;

final class EventCatalogController extends Controller
{
    public function __construct(
        View $view,
        private readonly EventRepository $events,
        private readonly CategoryRepository $categories,
        private readonly BookmarkRepository $bookmarks,
        private readonly Auth $auth,
    ) {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        $filter = CatalogFilter::fromRequest($request);

        return $this->renderPage('events/index', [
            'title' => 'Kegiatan',
            'filter' => $filter,
            'categories' => $this->categories->allActive(),
            'events' => $this->events->paginatePublicCatalog($filter),
        ]);
    }

    public function show(Request $request): Response
    {
        $route = (array) $request->attribute('route', []);
        $slug = (string) ($route['slug'] ?? '');
        $next = '/kegiatan/' . $slug;

        if (! $this->auth->check()) {
            return $this->redirect(url('/masuk?next=' . rawurlencode($next)));
        }

        $event = $this->events->findApprovedBySlug($slug);

        if ($event === null) {
            throw HttpException::notFound();
        }

        $this->events->incrementViewCount((int) $event['id']);

        $userId = (int) $this->auth->id();

        return $this->renderPage('events/show', [
            'title' => (string) $event['title'],
            'event' => $event,
            'isBookmarked' => $this->bookmarks->exists($userId, (int) $event['id']),
            'registrationUrl' => (string) ($event['registration_url'] ?? ''),
            'sourceUrl' => (string) ($event['source_url'] ?? ''),
            'sourceDomain' => UrlHelper::domain(isset($event['source_url']) ? (string) $event['source_url'] : null),
        ]);
    }
}
