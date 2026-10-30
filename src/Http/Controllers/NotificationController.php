<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Repositories\Contracts\EventRepository;
use App\Repositories\Contracts\UserRepository;

final class NotificationController extends Controller
{
    public function __construct(
        View $view,
        private readonly EventRepository $events,
        private readonly UserRepository $users,
        private readonly Auth $auth,
    ) {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        $user = $this->auth->user();
        $since = (string) ($user['events_seen_at'] ?? 'now');

        return $this->renderPage('notifications/index', [
            'title' => 'Notifikasi',
            'events' => $this->events->findNewSince($since, 50),
        ]);
    }

    public function markRead(Request $request): Response
    {
        $this->users->markEventsSeen((int) $this->auth->id());
        $this->auth->refresh();
        app()->session()->flash('status', 'Notifikasi ditandai sudah dibaca.');

        return $this->redirect(url('/notifikasi'));
    }
}
