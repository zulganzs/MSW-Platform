<?php

namespace App\Http\Controllers;

use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatusController extends Controller
{
    // Metis #6: forward-only transitions; anything else (backwards, skipping, unknown status) is 422
    private const ALLOWED_TRANSITIONS = [
        'assigned' => ['in_progress'],
        'in_progress' => ['completed'],
    ];

    public function updateStatus(Request $request, Report $report): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:in_progress,completed',
        ]);

        $user = $request->user();

        $isAssigned = $report->crews()->where('crew_user_id', $user->id)->exists();
        if (! $isAssigned) {
            return response()->json(['message' => 'Anda tidak ditugaskan ke laporan ini.'], 403);
        }

        $allowed = self::ALLOWED_TRANSITIONS[$report->status] ?? [];
        if (! in_array($validated['status'], $allowed, true)) {
            return response()->json(['message' => 'Perubahan status tidak valid.'], 422);
        }

        $report->update(['status' => $validated['status']]);

        return response()->json([
            'message' => 'Status diperbarui.',
            'report' => $report->fresh(),
        ], 200);
    }
}
