<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttachmentController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'report_id' => 'required|exists:reports,id',
            'file' => 'required|image|mimes:jpeg,jpg,png,webp|max:5120',
            'type' => 'required|in:submission,closure',
        ]);

        $report = Report::find($validated['report_id']);
        $user = $request->user();

        if ($validated['type'] === 'submission') {
            if ($user->role !== 'citizen') {
                return response()->json(['message' => 'Akses ditolak.'], 403);
            }
            if ($report->user_id !== $user->id) {
                return response()->json(['message' => 'Akses ditolak.'], 403);
            }
            if ($report->status !== 'submitted') {
                return response()->json(['message' => 'Laporan tidak dalam status submitted.'], 422);
            }
        }

        if ($validated['type'] === 'closure') {
            if ($user->role !== 'crew') {
                return response()->json(['message' => 'Akses ditolak.'], 403);
            }
            $isAssigned = $report->crews()->where('crew_user_id', $user->id)->exists();
            if (! $isAssigned) {
                return response()->json(['message' => 'Anda tidak ditugaskan ke laporan ini.'], 403);
            }
            if ($report->status !== 'in_progress') {
                return response()->json(['message' => 'Laporan tidak dalam status in_progress.'], 422);
            }
        }

        $path = $request->file('file')->store('attachments', 'public');

        $attachment = Attachment::create([
            'report_id' => $validated['report_id'],
            'user_id' => $user->id,
            'file_path' => $path,
            'type' => $validated['type'],
        ]);

        return response()->json($attachment, 201);
    }
}
