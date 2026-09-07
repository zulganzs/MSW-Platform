<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Report;
use Illuminate\Http\Request;

class CrewController extends Controller
{
    public function dashboard(Request $request)
    {
        $status = $request->query('status');

        $query = Report::with('category')->whereHas('crews', function ($q) {
            $q->where('crew_user_id', auth()->id());
        });

        if ($status && in_array($status, ['assigned', 'in_progress', 'completed'])) {
            $query->where('status', $status);
        }

        $reports = $query->latest()->paginate(15);

        $counts = [
            'all' => Report::whereHas('crews', fn ($q) => $q->where('crew_user_id', auth()->id()))->count(),
            'assigned' => Report::whereHas('crews', fn ($q) => $q->where('crew_user_id', auth()->id()))->where('status', 'assigned')->count(),
            'in_progress' => Report::whereHas('crews', fn ($q) => $q->where('crew_user_id', auth()->id()))->where('status', 'in_progress')->count(),
            'completed' => Report::whereHas('crews', fn ($q) => $q->where('crew_user_id', auth()->id()))->where('status', 'completed')->count(),
        ];

        return view('crew.dashboard', [
            'reports' => $reports,
            'currentStatus' => $status,
            'counts' => $counts,
        ]);
    }

    public function reportDetail(Report $report)
    {
        if (! $report->crews()->where('crew_user_id', auth()->id())->exists()) {
            abort(403);
        }

        $report->load(['attachments', 'crews', 'category']);

        return view('crew.reports.show', ['report' => $report]);
    }

    public function updateStatus(Request $request, Report $report)
    {
        if (! $report->crews()->where('crew_user_id', auth()->id())->exists()) {
            abort(403);
        }

        $validated = $request->validate([
            'status' => 'required|in:in_progress,completed',
        ]);

        $newStatus = $validated['status'];

        if ($report->status === 'assigned' && $newStatus === 'in_progress') {
            $report->update(['status' => 'in_progress']);

            return back()->with('success', 'Status laporan berhasil diubah menjadi Dikerjakan.');
        }

        // Note: completion through this method is only allowed if it has a closure attachment (verified below if needed, but UI prevents it usually. Upload closure does this directly usually).
        // For direct transition from in_progress to completed without new file (if they uploaded before)
        if ($report->status === 'in_progress' && $newStatus === 'completed') {
            $hasClosure = $report->attachments()->where('type', 'closure')->exists();
            if (! $hasClosure) {
                return back()->with('error', 'Silakan unggah bukti penyelesaian terlebih dahulu.');
            }
            $report->update(['status' => 'completed']);

            return back()->with('success', 'Laporan berhasil diselesaikan.');
        }

        return back()->with('error', 'Perubahan status tidak diizinkan.');
    }

    public function uploadClosure(Request $request, Report $report)
    {
        if (! $report->crews()->where('crew_user_id', auth()->id())->exists()) {
            abort(403);
        }

        if ($report->status !== 'in_progress') {
            return back()->with('error', 'Hanya laporan yang sedang dikerjakan yang dapat diunggah bukti.');
        }

        $request->validate([
            'file' => 'required|image|max:5120',
        ]);

        $path = $request->file('file')->store('closures', 'public');

        Attachment::create([
            'report_id' => $report->id,
            'user_id' => auth()->id(),
            'file_path' => $path,
            'type' => 'closure',
        ]);

        if ($request->has('status') && $request->input('status') === 'completed') {
            $report->update(['status' => 'completed']);

            return back()->with('success', 'Bukti penyelesaian berhasil diunggah dan laporan diselesaikan.');
        }

        return back()->with('success', 'Bukti penyelesaian berhasil diunggah.');
    }
}
