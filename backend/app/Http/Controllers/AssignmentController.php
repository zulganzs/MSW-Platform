<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    public function assign(Request $request, Report $report): JsonResponse
    {
        $validated = $request->validate([
            'crew_user_id' => 'required|exists:users,id',
        ]);

        $crewUser = User::find($validated['crew_user_id']);
        if ($crewUser->role !== 'crew') {
            return response()->json(['message' => 'Pengguna bukan anggota kru.'], 422);
        }

        try {
            $report->crews()->attach($validated['crew_user_id'], [
                'staff_user_id' => $request->user('sanctum')->id,
                'assigned_at' => now(),
            ]);
        } catch (QueryException $e) {
            // ponytail: SQLite unique violation detection via errorInfo[1]/message; switch to $e->getCode() === '23000' check only when on MySQL/Postgres
            // Unique index on (report_id, crew_user_id) is the last line of defense against double-assignment.
            $isUniqueViolation = $e->errorInfo[1] === 19 || str_contains($e->getMessage(), 'UNIQUE');
            if (! $isUniqueViolation) {
                throw $e;
            }

            return response()->json(['message' => 'Kru sudah ditugaskan ke laporan ini.'], 409);
        }

        $report->update(['status' => 'assigned']);

        return response()->json([
            'message' => 'Kru berhasil ditugaskan.',
            'report' => $report->fresh(),
        ], 200);
    }
}
