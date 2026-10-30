<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\EventRepository;
use App\Repositories\Contracts\ModerationLogRepository;
use App\Services\ModerationService;
use App\Services\RevisionTokenService;

final class AdminModerationController extends Controller
{
    public function __construct(
        View $view,
        private readonly EventRepository $events,
        private readonly ModerationLogRepository $logs,
        private readonly ModerationService $moderation,
        private readonly RevisionTokenService $tokens,
    ) {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        return $this->renderPage('admin/moderation/index', [
            'title' => 'Moderasi',
            'events' => $this->events->paginateReviewQueue(),
        ]);
    }

    public function show(Request $request): Response
    {
        $event = $this->findEvent($request);

        return $this->renderPage('admin/moderation/show', [
            'title' => 'Tinjau ' . $event['title'],
            'event' => $event,
            'logs' => $this->logs->forEvent((int) $event['id']),
            'revisionToken' => (string) (flash('revision_token') ?? ''),
            'revisionTokenEventId' => (int) (flash('revision_token_event_id') ?? 0),
        ]);
    }

    public function approve(Request $request): Response
    {
        $event = $this->findEvent($request);
        $this->moderation->approve((int) $event['id'], $this->actorId(), $request->ip(), $request->header('user-agent'));
        app()->session()->flash('status', 'Kegiatan disetujui dan tayang.');

        return $this->redirect(url('/admin/moderasi/' . $event['id']));
    }

    public function reject(Request $request): Response
    {
        $event = $this->findEvent($request);
        $this->moderation->reject(
            (int) $event['id'],
            $this->actorId(),
            $request->string('reason'),
            $request->ip(),
            $request->header('user-agent'),
        );
        app()->session()->flash('status', 'Kegiatan ditolak.');

        return $this->redirect(url('/admin/moderasi/' . $event['id']));
    }

    public function requestChanges(Request $request): Response
    {
        $event = $this->findEvent($request);
        $result = $this->moderation->requestChanges(
            (int) $event['id'],
            $this->actorId(),
            $request->string('reason'),
            $request->ip(),
            $request->header('user-agent'),
        );
        app()->session()->flash('status', 'Permintaan revisi dibuat. Salin tautan sebelum meninggalkan halaman.');
        app()->session()->flash('revision_token', $result['token']);
        app()->session()->flash('revision_token_event_id', (int) $event['id']);

        return $this->redirect(url('/admin/moderasi/' . $event['id']));
    }

    public function reissueToken(Request $request): Response
    {
        $event = $this->findEvent($request);
        $result = $this->moderation->reissueToken(
            (int) $event['id'],
            $this->actorId(),
            $request->ip(),
            $request->header('user-agent'),
        );
        app()->session()->flash('status', 'Tautan revisi baru diterbitkan.');
        app()->session()->flash('revision_token', $result['token']);
        app()->session()->flash('revision_token_event_id', (int) $event['id']);

        return $this->redirect(url('/admin/moderasi/' . $event['id']));
    }

    public function archive(Request $request): Response
    {
        $event = $this->findEvent($request);
        $this->moderation->archive((int) $event['id'], $this->actorId(), $request->ip(), $request->header('user-agent'));
        app()->session()->flash('status', 'Kegiatan diarsipkan. Data dan simpanan tetap utuh.');

        return $this->redirect(url('/admin/moderasi'));
    }

    /** @return array<string, mixed> */
    private function findEvent(Request $request): array
    {
        $event = $this->events->findById((int) (($request->attribute('route', []))['event'] ?? 0));

        if ($event === null) {
            $this->abort(404);
        }

        return $event;
    }

    private function actorId(): int
    {
        return (int) auth_user()['id'];
    }

}
