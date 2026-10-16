<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\StoreReportRequest;
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
    public function create(Request $request, \App\Models\Event $event): View
    {
        $this->authorize('report', $event);

        return view('user.reports.create', [
            'event' => $event,
            'existing' => $event->reports()->where('reporter_user_id', $request->user()->id)->first(),
        ]);
    }

    public function store(StoreReportRequest $request, ReportService $reports): RedirectResponse
    {
        $report = $reports->report(
            reporter: $request->user(),
            event: $request->route('event'),
            reason: $request->reason(),
            description: $request->input('description'),
        );

        return redirect()
            ->route('events.show', $request->route('event')->slug)
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
