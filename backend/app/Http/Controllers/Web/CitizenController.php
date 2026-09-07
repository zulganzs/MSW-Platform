<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Category;
use App\Models\Report;
use Illuminate\Http\Request;

class CitizenController extends Controller
{
    public function dashboard()
    {
        $userId = auth()->id();

        $stats = [
            'total' => Report::where('user_id', $userId)->count(),
            'submitted' => Report::where('user_id', $userId)->where('status', 'submitted')->count(),
            'in_progress' => Report::where('user_id', $userId)->whereIn('status', ['assigned', 'in_progress'])->count(),
            'completed' => Report::where('user_id', $userId)->where('status', 'completed')->count(),
        ];

        $recentReports = Report::with('category')
            ->where('user_id', $userId)
            ->latest()
            ->take(5)
            ->get();

        $mapReports = Report::with('category')
            ->whereIn('visibility', ['public', 'anonymous'])
            ->orWhere('user_id', $userId)
            ->whereNotNull('latitude')
            ->get();

        return view('citizen.dashboard', compact('stats', 'recentReports', 'mapReports'));
    }

    public function reportList(Request $request)
    {
        $query = Report::with('category')
            ->where(function ($q) {
                $q->where('user_id', auth()->id())
                    ->orWhere('visibility', 'public')
                    ->orWhere('visibility', 'anonymous');
            });

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        $reports = $query->latest()->paginate(15);

        // Apply visibility matrix (strip user_id for anonymous reports not owned by the user)
        $reports->getCollection()->transform(function ($report) {
            if ($report->visibility === 'anonymous' && $report->user_id !== auth()->id()) {
                $report->user_id = null;
                $report->unsetRelation('user');
            }

            return $report;
        });

        return view('citizen.reports.index', [
            'reports' => $reports,
            'categories' => Category::all(),
        ]);
    }

    public function reportCreate()
    {
        return view('citizen.reports.create', ['categories' => Category::all()]);
    }

    public function reportStore(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'description' => 'required|string|min:10|max:500',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'visibility' => 'required|in:public,private,anonymous',
            'photos' => 'nullable|array',
            'photos.*' => 'image|mimes:jpg,png|max:5120',
        ]);

        $report = Report::create([
            'user_id' => auth()->id(),
            'category_id' => $validated['category_id'],
            'description' => $validated['description'],
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'visibility' => $validated['visibility'],
            'status' => 'submitted',
        ]);

        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $path = $photo->storePublicly('attachments/'.$report->id, 'public');
                Attachment::create([
                    'report_id' => $report->id,
                    'user_id' => auth()->id(),
                    'file_path' => $path,
                    'type' => 'submission',
                ]);
            }
        }

        return redirect()->route('citizen.reports.show', $report)
            ->with('success', 'Laporan berhasil dikirim!');
    }

    public function reportShow(Report $report)
    {
        // Apply visibility access matrix
        if ($report->visibility === 'private' && $report->user_id !== auth()->id()) {
            abort(403);
        }

        $report->load(['category', 'attachments', 'crews']);

        // Apply visibility matrix (strip user info for anonymous reports not owned by the user)
        if ($report->visibility === 'anonymous' && $report->user_id !== auth()->id()) {
            $report->user_id = null;
            $report->unsetRelation('user');
        }

        return view('citizen.reports.show', ['report' => $report]);
    }

    public function reportDestroy(Request $request, Report $report)
    {
        if ($report->user_id !== $request->user()->id) {
            abort(403, 'Akses ditolak.');
        }

        if ($report->status !== 'submitted') {
            return back()->with('error', 'Laporan tidak dapat dihapus karena sudah diproses.');
        }

        // Delete stored attachment files (DB rows cascade on report delete)
        foreach ($report->attachments as $attachment) {
            \Storage::disk('public')->delete($attachment->file_path);
        }

        $report->delete();

        return redirect()->route('citizen.reports.index')
            ->with('success', 'Laporan berhasil dihapus.');
    }

    public function map()
    {
        $reports = Report::with('category')
            ->whereIn('visibility', ['public', 'anonymous'])
            ->get();

        return view('citizen.map', compact('reports'));
    }
}
