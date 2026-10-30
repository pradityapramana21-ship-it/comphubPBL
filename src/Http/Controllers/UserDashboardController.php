<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Repositories\Contracts\BookmarkRepository;
use App\Repositories\Contracts\EventRepository;

final class UserDashboardController extends Controller
{
    public function __construct(
        View $view,
        private readonly BookmarkRepository $bookmarks,
        private readonly EventRepository $events,
        private readonly Auth $auth,
    ) {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        $user = $this->auth->user();

        return $this->renderPage('user/dashboard', [
            'title' => 'Dasbor',
            'user' => $user,
            'savedCount' => $this->bookmarks->countForUser((int) $user['id']),
            'newCount' => $this->events->countNewSince((string) ($user['events_seen_at'] ?? 'now')),
        ]);
    }

    public function saved(Request $request): Response
    {
        $page = max(1, (int) $request->input('page', 1));

        return $this->renderPage('user/saved', [
            'title' => 'Kegiatan tersimpan',
            'events' => $this->bookmarks->paginateForUser((int) $this->auth->id(), 12, $page),
        ]);
    }
}
