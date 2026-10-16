<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventReport;
use App\Repositories\Contracts\ReportRepository;
use App\Services\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Antrean laporan dari pengguna.
 *
 * Identitas pelapor terlihat di sini dan TIDAK PERNAH dikirim ke
 * penyedia kegiatan. Yang diteruskan ke penyedia hanya statusnya: laporan
 * sedang ditinjau atau sudah ditangani. Kalau identitasnya bocor,
 * orang akan berhenti melapor (Panduan §4.5).
 */
class ReportReviewController extends Controller
{
    public function index(Request $request, ReportRepository $reports): View
    {
        return view('admin.reports.index', [
            'pending' => $reports->paginateOpenQueue(perPage: 15),
            'history' => $reports->paginateForAdmin(
                array_filter(['status' => $request->query('status')]),
                perPage: 15,
            ),
            'openCount' => $reports->countOpen(),
        ]);
    }

    public function open(Request $request, EventReport $report, ReportService $service): RedirectResponse
    {
        $this->authorize('resolve', $report);

        $service->open($request->user(), $report);

        return back()->with('success', 'Laporan ditandai sedang ditinjau.');
    }

    public function resolve(Request $request, EventReport $report, ReportService $service): RedirectResponse
    {
        $this->authorize('resolve', $report);

        $request->validate([
            'note' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'note.required' => 'Tuliskan catatan penanganan. Ini yang dibaca penyaji kegiatan.',
            'note.min' => 'Catatan minimal 5 karakter.',
        ]);

        $service->resolve($request->user(), $report, (string) $request->input('note'));

        $this->flashSuccess('Laporan ditandai selesai. Catatan penanganan tersimpan.');

        return back();
    }

    public function dismiss(Request $request, EventReport $report, ReportService $service): RedirectResponse
    {
        $this->authorize('resolve', $report);

        $request->validate([
            'note' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $service->dismiss($request->user(), $report, (string) $request->input('note'));

        return back()->with('success', 'Laporan tidak diteruskan.');
    }
}
