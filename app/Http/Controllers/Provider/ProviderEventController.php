<?php

declare(strict_types=1);

namespace App\Http\Controllers\Provider;

use App\DataTransferObjects\CatalogFilter;
use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Event\StoreEventRequest;
use App\Http\Requests\Event\UpdateEventRequest;
use App\Models\Event;
use App\Repositories\Contracts\EventRepository;
use App\Services\EventService;
use App\Support\SlugGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CRUD kegiatan milik penyedia sendiri.
 *
 * Setiap aksi melewati POLICY lebih dulu (`authorize`), lalu service.
 * Urutan itu tidak boleh ditukar: policy menjawab "boleh atau tidak",
 * service menjawab "apa akibatnya kalau boleh".
 */
class ProviderEventController extends Controller
{
    public function index(Request $request, EventRepository $events): View
    {
        $profile = $this->profileFor($request->user());

        abort_if($profile === null, 403);

        return view('provider.events.index', [
            'profile' => $profile,
            'events' => $events->paginateForProvider(
                $profile->id,
                CatalogFilter::fromQuery($request->query())->withoutDeadlineFilter(),
            ),
            'filter' => CatalogFilter::fromQuery($request->query()),
            'categories' => $this->categories(),
        ]);
    }

    public function create(Request $request): View
    {
        $profile = $this->profileFor($request->user());

        abort_if($profile === null, 403);

        // Buat kegiatan hanya untuk penyedia yang sudah terverifikasi.
        // Menunggu verifikasi bukan alasan membuka jalur sodium: tanpa
        // verifikasi, nama organisasi yang muncul di halaman pengajuan
        // tidak bisa dipertanggungjawabkan ke siapa pun.
        $this->authorize('create', Event::class);

        return view('provider.events.create', [
            'profile' => $profile,
            'categories' => $this->categories(),
        ]);
    }

    public function store(StoreEventRequest $request, EventService $events, SlugGenerator $slugs): RedirectResponse
    {
        $profile = $this->profileFor($request->user());

        abort_if($profile === null, 403);

        $event = $events->createDraft($profile, $request->eventAttributes() + [
            'slug' => $slugs->unique((string) $request->input('title'), 'events'),
        ]);

        return redirect()
            ->route('provider.events.edit', $event)
            ->with('success', 'Draf tersimpan. Periksa sekali lagi, lalu ajukan untuk ditinjau.');
    }

    public function show(Request $request, Event $event, EventService $events): View
    {
        $event = $this->ownEvent($event);

        $this->authorize('view', $event);

        return view('provider.events.show', [
            'event' => $event,
            'history' => $events->historyFor($event),
        ]);
    }

    public function edit(Request $request, Event $event): View
    {
        $event = $this->ownEvent($event);

        $this->authorize('update', $event);

        return view('provider.events.edit', [
            'event' => $event,
            'categories' => $this->categories(),
            'canSubmit' => $event->status->canTransitionTo(EventStatus::Pending),
        ]);
    }

    public function update(UpdateEventRequest $request, Event $event, EventService $events, SlugGenerator $slugs): RedirectResponse
    {
        $profile = $this->profileFor($request->user());

        abort_if($profile === null, 403);

        $event = $this->ownEvent($event);

        // Slug hanya dibuat ulang kalau judul berubah. Kalau tidak, tautan
        // yang sudah dibagikan orang tetap hidup.
        $attributes = $request->eventAttributes();

        if ($attributes['title'] !== $event->title) {
            $attributes['slug'] = $slugs->unique($attributes['title'], 'events', $event->id);
        }

        $events->update($profile, $event, $attributes);

        return back()->with('success', 'Perubahan tersimpan.');
    }

    /**
     * Ajukan draf untuk ditinjau.
     *
     * Di sinilah mesin moderasi berjalan. Pesan yang ditampilkan
     * selalu menjelaskan posisi pengajuan, karena "ditolak otomatis"
     * tanpa alasan akan membuat penyedia menebak-tebak.
     */
    public function submit(Request $request, Event $event, EventService $events): RedirectResponse
    {
        $profile = $this->profileFor($request->user());

        abort_if($profile === null, 403);

        $event = $this->ownEvent($event);

        $this->authorize('submit', $event);

        $submitted = $events->submit($profile, $event);

        if ($submitted->status === EventStatus::Rejected) {
            $this->flashFailure(
                'Pengajuan ditolak otomatis oleh pemeriksaan otomatis. ' . $submitted->rejection_reason,
            );

            return redirect()->route('provider.events.edit', $submitted);
        }

        $this->flashSuccess('Pengajuan masuk antrean tinjauan administrator.');

        return redirect()->route('provider.events.show', $submitted);
    }

    /**
     * Pastikan kegiatan ini milik organisasi yang sedang login.
     *
     * Mengembalikan 404, BUKAN 403, dan itu disengaja. Kalau kegiatan
     * milik orang lain diberi 403, jawabannya sudah mengonfirmasi bahwa
     * kegiatan itu ADA. Dengan 404 untuk keduanya, penyedia lain dan
     * pengunjung awam tidak bisa dibedakan (Panduan §6.5, TC-PROV-02).
     */
    private function ownEvent(Event $event): Event
    {
        $profile = $this->profileFor(auth()->user());

        abort_if(
            $profile === null || $event->provider_profile_id !== $profile->id,
            404,
            'Kegiatan tidak ditemukan.',
        );

        return $event;
    }

    /** Menarik kegiatan yang belum tayang. */
    public function destroy(Request $request, Event $event, EventService $events): RedirectResponse
    {
        $profile = $this->profileFor($request->user());

        abort_if($profile === null, 403);

        $event = $this->ownEvent($event);

        $this->authorize('delete', $event);

        $events->withdraw($profile, $event);

        return redirect()
            ->route('provider.events.index')
            ->with('success', 'Kegiatan ditarik dan tidak lagi tampil.');
    }
}
