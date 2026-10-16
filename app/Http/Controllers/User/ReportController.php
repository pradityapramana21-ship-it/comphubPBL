<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\StoreReportRequest;
use App\Models\Event;
use App\Models\EventReport;
use App\Services\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pelaporan kegiatan bermasalah.
 *
 * Identitas pelapor disimpan tapi TIDAK pernah dikirim ke penyedia.
 * Yang tampil di halaman penyedia hanyalah "laporan sedang diproses",
 * tanpa nama siapa pun (Panduan §4.5).
 */
class ReportController extends Controller
{
    public function create(Request $request, Event $event): View
    {
        $this->authorize('report', $event);

        return view('user.reports.create', [
            'event' => $event,
            'existing' => $event->reports()->where('reporter_user_id', $request->user()->id)->first(),
        ]);
    }

    /**
     * Parameter $event WAJIB ada di tanda tangan method.
     *
     * Pengikatan model Laravel bekerja berdasarkan tanda tangan controller:
     * kalau method tidak menyebut `Event $event`, middleware SubstituteBindings
     * tidak punya alasan untuk mengganti "12" di URL dengan objek Event, dan
     * Form Request hanya melihat string. Form Request pun memeriksa
     * `$event instanceof Event`, sehingga authorize() selalu menolak dan
     * mahasiswa tidak bisa melapor sama sekali.
     */
    public function store(StoreReportRequest $request, Event $event, ReportService $reports): RedirectResponse
    {
        $reports->report(
            reporter: $request->user(),
            event: $event,
            reason: $request->reason(),
            description: $request->input('description'),
        );

        return redirect()
            ->route('events.show', $event->slug)
            ->with('success', 'Laporan terkirim. Terima kasih sudah membantu menjaga informasi di sini tetap akurat.');
    }

    /** Pelapor boleh menarik laporannya sendiri selama belum diproses. */
    public function destroy(Request $request, EventReport $report, ReportService $reports): RedirectResponse
    {
        $this->authorize('withdraw', $report);

        $reports->withdraw($request->user(), $report);

        return back()->with('success', 'Laporan ditarik.');
    }
}
