<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StaffController extends Controller
{
    public function dashboard()
    {
        $reports = Report::with('category', 'user', 'attachments')
            ->where('status', 'submitted')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $stats = [
            'total' => Report::count(),
            'submitted' => Report::where('status', 'submitted')->count(),
            'in_progress' => Report::where('status', 'in_progress')->count(),
            'completed' => Report::where('status', 'completed')->count(),
        ];

        // Find potential duplicates: reports with same category within 500m of each other, status != completed
        // Haversine formula
        $duplicates = DB::select("
            SELECT * FROM (
                SELECT r1.id as id1, r2.id as id2, r1.description as desc1, r2.description as desc2,
                (6371 * acos(
                    cos(radians(r1.latitude)) * cos(radians(r2.latitude)) *
                    cos(radians(r2.longitude) - radians(r1.longitude)) +
                    sin(radians(r1.latitude)) * sin(radians(r2.latitude))
                )) * 1000 AS distance
                FROM reports r1
                JOIN reports r2 ON r1.id < r2.id
                    AND r1.category_id = r2.category_id
                    AND r1.status != 'completed'
                    AND r2.status != 'completed'
            ) AS duplicates
            WHERE distance < 500
            ORDER BY distance ASC
            LIMIT 10
        ");

        $duplicatesCollection = collect($duplicates);

        // Get crew for assignment
        $crewUsers = User::where('role', 'crew')
            ->withCount(['assignedReports' => function ($query) {
                $query->whereIn('status', ['assigned', 'in_progress']);
            }])
            ->get();

        return view('staff.dashboard', [
            'reports' => $reports,
            'stats' => $stats,
            'duplicates' => $duplicatesCollection,
            'crewUsers' => $crewUsers,
        ]);
    }

    public function assignCrew(Request $request, Report $report)
    {
        $validated = $request->validate([
            'crew_user_id' => 'required|exists:users,id',
        ]);

        $crew = User::where('role', 'crew')->findOrFail($validated['crew_user_id']);

        try {
            $report->crews()->attach($crew->id, [
                'staff_user_id' => auth()->id(),
                'assigned_at' => now(),
            ]);

            $report->update(['status' => 'assigned']);

            return back()->with('success', 'Petugas berhasil ditugaskan');
        } catch (QueryException $e) {
            // Check for unique constraint violation (code 23000)
            if ($e->getCode() == 23000) {
                return back()->with('error', 'Laporan sudah ditugaskan ke petugas ini');
            }
            throw $e;
        }
    }
}
