<?php

declare(strict_types=1);

namespace App\Http\Controllers\Provider;

use App\DataTransferObjects\CatalogFilter;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Repositories\Contracts\EventRepository;
use App\Repositories\Contracts\ModerationLogRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Dasbor penyedia.
 *
 * Halaman pertama setelah masuk untuk seorang penyelenggara. Fokusnya satu
 * hal: status pengajuan mereka. Bukan angka statistik yang menggoda,
 * melainkan "di mana sekarang posisinya".
 */
class ProviderDashboardController extends Controller
{
    public function __invoke(
        Request $request,
        EventRepository $events,
        ModerationLogRepository $moderationLogs,
    ): View {
        $profile = $this->profileFor($request->user());

        abort_if($profile === null, 403, 'Akun ini tidak terdaftar sebagai penyedia kegiatan.');

        $filter = CatalogFilter::fromQuery([])->withoutDeadlineFilter();

        $all = $events->paginateForProvider($profile->id, $filter);

        $perStatus = [];

        foreach ($all->getCollection() as $event) {
            $perStatus[$event->status->value] = ($perStatus[$event->status->value] ?? 0) + 1;
        }

        return view('provider.dashboard', [
            'profile' => $profile,
            'recent' => $all,
            'perStatus' => $perStatus,
            'totalTersimpan' => (int) $all->getCollection()->sum('bookmark_count'),
            'totalTayangan' => (int) $all->getCollection()->sum('view_count'),
            'needsAction' => $this->needsAction($all->getCollection()),
            'ruleHits' => $moderationLogs->countByRuleKey(),
        ]);
    }

    /**
     * Kegiatan yang butuh sesuatu dari penyedia.
     *
     * Daftar ini adalah alasan utama halaman ini ada: tanpa itu,
     * penyedia cuma harus complain "kenapa belum ditinjau?".
     *
     * @param  Collection<int, Event>  $events
     * @return Collection<int, Event>
     */
    private function needsAction(iterable $events): Collection
    {
        return collect($events)
            ->filter(fn ($event): bool => in_array(
                $event->status->value,
                ['changes_requested', 'rejected'],
                true,
            ))
            ->values();
    }
}
