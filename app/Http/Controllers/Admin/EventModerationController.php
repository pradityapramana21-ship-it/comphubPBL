<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DataTransferObjects\CatalogFilter;
use App\Enums\EventStatus;
use App\Enums\ModerationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ModerationDecisionRequest;
use App\Models\Event;
use App\Repositories\Contracts\EventRepository;
use App\Repositories\Contracts\ModerationLogRepository;
use App\Services\EventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Antrean tinjauan kegiatan.
 *
 * Ini tempat keputusan yang paling berdampak di aplikasi terjadi:
 * Approve berarti kegiatan yang dilihat banyak orang.
 *
 * Karena itu halaman ini menampilkan alasan penandaan otomatis DULU,
 * sebelum admin mengambil keputusan. Admin yang tahu sebuah kegiatan
 * kena aturan duplikasi punya peluang lebih besar untuk menilai dengan
 * benar daripada yang hanya melihat judul dan deskripsi (Panduan §7.2).
 */
class EventModerationController extends Controller
{
    public function index(Request $request, EventRepository $events): View
    {
        return view('admin.events.index', [
            'queue' => $events->paginateReviewQueue(perPage: 15),
            'all' => $events->paginateForAdmin(
                CatalogFilter::fromQuery($request->query())->withoutDeadlineFilter(),
            ),
            'filter' => CatalogFilter::fromQuery($request->query()),
            'categories' => $this->categories(),
        ]);
    }

    public function show(Event $event, ModerationLogRepository $logs): View
    {
        $this->authorize('view', $event);

        return view('admin.events.show', [
            'event' => $event->load(['providerProfile.user', 'category']),
            'history' => $logs->forEvent($event->id),
            'autoFlags' => $logs->forEvent($event->id)->where('actor_user_id', null),
            'reports' => $event->reports()->with('reporter')->latest()->get(),
            'allowedDecisions' => $this->allowedDecisions($event),
        ]);
    }

    /**
     * Ambil keputusan: setujui, tolak, minta perbaikan, atau arsipkan.
     *
     * Satu endpoint untuk semuanya, dengan alasan yang WAJIB diisi
     * setiap kali kegiatan tidak disetujui. Admin yang menekan "tolak"
     * tanpa alasan tidak akan pernah mendapat balasan yang bisa
     * ditindaklanjuti.
     */
    public function moderate(ModerationDecisionRequest $request, Event $event, EventService $events): RedirectResponse
    {
        $decision = $request->action();
        $reason = $request->reasonText();

        match ($decision) {
            ModerationAction::Approve => $events->approve($request->user(), $event, $reason),
            ModerationAction::Reject => $events->reject($request->user(), $event, (string) $reason),
            ModerationAction::RequestChanges => $events->requestChanges($request->user(), $event, (string) $reason),
            ModerationAction::Archive => $events->archive($request->user(), $event, $reason),
            default => abort(422, 'Keputusan ini tidak bisa dilakukan dari antrean tinjauan.'),
        };

        $this->flashSuccess(match ($decision) {
            ModerationAction::Approve => 'Kegiatan disetujui dan sekarang tampil untuk publik.',
            ModerationAction::Reject => 'Kegiatan ditolak. Penyedia menerima alasannya dan bisa mengajukan ulang.',
            ModerationAction::RequestChanges => 'Penyedia diminta memperbaiki kegiatan.',
            default => 'Kegiatan diarsipkan.',
        });

        return redirect()->route('admin.events.index');
    }

    /**
     * @return list<ModerationAction>
     */
    private function allowedDecisions(Event $event): array
    {
        return array_values(array_filter(
            [
                ModerationAction::Approve,
                ModerationAction::RequestChanges,
                ModerationAction::Reject,
                ModerationAction::Archive,
            ],
            static fn (ModerationAction $action): bool => match ($action) {
                ModerationAction::Approve => $event->status->canTransitionTo(EventStatus::Approved),
                ModerationAction::RequestChanges => $event->status->canTransitionTo(EventStatus::ChangesRequested),
                ModerationAction::Reject => $event->status->canTransitionTo(EventStatus::Rejected),
                ModerationAction::Archive => $event->status->canTransitionTo(EventStatus::Archived),
                default => false,
            },
        ));
    }
}
