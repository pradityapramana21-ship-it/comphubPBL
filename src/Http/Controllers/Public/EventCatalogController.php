<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\DataTransferObjects\CatalogFilter;
use App\Enums\EventType;
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
        return $this->catalog($request, null);
    }

    public function competition(Request $request): Response
    {
        return $this->catalog($request, EventType::Competition);
    }

    public function webinar(Request $request): Response
    {
        return $this->catalog($request, EventType::Webinar);
    }

    public function seminar(Request $request): Response
    {
        return $this->catalog($request, EventType::Seminar);
    }

    private function catalog(Request $request, ?EventType $type): Response
    {
        $source = CatalogFilter::fromRequest($request);
        $filter = new CatalogFilter(
            q: $source->q,
            category: $source->category,
            mode: $source->mode,
            status: $source->status,
            sort: $source->sort,
            page: $source->page,
            type: $type?->value ?? $source->type,
            month: $source->month,
        );

        return $this->renderPage('events/index', [
            'title' => $type?->label() ?? 'Kegiatan',
            'filter' => $filter,
            'eventType' => $type,
            'types' => EventType::cases(),
            'categories' => $this->categories->allActive(),
            'months' => $this->events->availableMonths(),
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
