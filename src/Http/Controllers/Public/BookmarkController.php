<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\BookmarkRepository;
use App\Repositories\Contracts\EventRepository;
use App\Services\Access;

final class BookmarkController extends Controller
{
    public function __construct(
        View $view,
        private readonly EventRepository $events,
        private readonly BookmarkRepository $bookmarks,
        private readonly Auth $auth,
        private readonly Access $access,
    ) {
        parent::__construct($view);
    }

    public function store(Request $request): Response
    {
        $event = $this->event($request);
        $userId = (int) $this->auth->id();

        if (! $this->access->canBookmark($this->auth->user(), $event)) {
            throw HttpException::forbidden();
        }

        if ($this->bookmarks->add($userId, (int) $event['id'])) {
            $this->events->incrementBookmarkCount((int) $event['id']);
        }

        app()->session()->flash('status', 'Kegiatan disimpan.');

        return $this->redirect(url('/kegiatan/' . $event['slug']));
    }

    public function destroy(Request $request): Response
    {
        $event = $this->event($request);
        $userId = (int) $this->auth->id();

        if ($this->bookmarks->remove($userId, (int) $event['id'])) {
            $this->events->decrementBookmarkCount((int) $event['id']);
        }

        app()->session()->flash('status', 'Kegiatan dihapus dari simpanan.');

        return $this->redirect(url('/kegiatan/' . $event['slug']));
    }

    /** @return array<string, mixed> */
    private function event(Request $request): array
    {
        $route = (array) $request->attribute('route', []);
        $event = $this->events->findById((int) ($route['event'] ?? 0));

        if ($event === null || ($event['status'] ?? '') !== 'approved') {
            throw HttpException::notFound();
        }

        return $event;
    }
}
