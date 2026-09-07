<?php

namespace App\Http\Controllers;

use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Report::query()->with(['user', 'category'])->latest('created_at');

        // ponytail: user('sanctum') because default guard is web (session) —
        // on public routes (no auth:sanctum middleware) only the sanctum
        // guard resolves Bearer tokens; upgrade path: set AUTH_GUARD=sanctum.
        $user = $request->user('sanctum');

        if ($user && in_array($user->role, ['staff', 'crew'], true)) {
            // staff/crew see all reports
        } elseif ($user) {
            // citizen: public + anonymous + own private
            $query->where(function ($q) use ($user) {
                $q->whereIn('visibility', ['public', 'anonymous'])
                    ->orWhere(function ($q2) use ($user) {
                        $q2->where('visibility', 'private')->where('user_id', $user->id);
                    });
            });
        } else {
            // unauthenticated: public + anonymous only
            $query->whereIn('visibility', ['public', 'anonymous']);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        $data = $query->paginate(15)->toArray();

        foreach ($data['data'] as &$item) {
            if (($item['visibility'] ?? null) === 'anonymous') {
                unset($item['user_id'], $item['user']);
            }
        }

        return response()->json($data);
    }

    public function show(Request $request, Report $report): JsonResponse
    {
        $user = $request->user('sanctum');

        if ($report->visibility === 'private') {
            if (! $user) {
                return response()->json(['message' => 'Akses ditolak.'], 403);
            }

            $isOwner = $report->user_id === $user->id;
            $isStaffOrCrew = in_array($user->role, ['staff', 'crew'], true);

            if (! $isOwner && ! $isStaffOrCrew) {
                return response()->json(['message' => 'Akses ditolak.'], 403);
            }
        }

        $report->load(['user', 'category', 'attachments', 'crews']);
        $data = $report->toArray();

        if ($report->visibility === 'anonymous') {
            unset($data['user_id'], $data['user']);
        }

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'description' => 'required|string|min:10',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'visibility' => 'required|in:public,private,anonymous',
        ]);

        $report = Report::create([
            'category_id' => $validated['category_id'],
            'user_id' => $request->user()->id,
            'description' => $validated['description'],
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'status' => 'submitted',
            'visibility' => $validated['visibility'],
        ]);

        $report->load(['user', 'category', 'attachments', 'crews']);
        $data = $report->toArray();

        if ($report->visibility === 'anonymous') {
            unset($data['user_id'], $data['user']);
        }

        return response()->json($data, 201);
    }

    public function destroy(Request $request, Report $report): JsonResponse
    {
        if ($report->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        if ($report->status !== 'submitted') {
            return response()->json(['message' => 'Laporan tidak dapat dihapus karena sudah diproses.'], 403);
        }

        $report->delete();

        return response()->json(['message' => 'Laporan berhasil dihapus'], 200);
    }

    /**
     * Crew-only: list reports assigned to the authenticated crew user.
     * Leverages User::assignedReports() BelongsToMany (crew_user_id -> report_id).
     */
    public function crewReports(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $user->assignedReports()
            ->with(['user', 'category'])
            ->latest('created_at')
            ->paginate(15)
            ->toArray();

        foreach ($data['data'] as &$item) {
            if (($item['visibility'] ?? null) === 'anonymous') {
                unset($item['user_id'], $item['user']);
            }
        }

        return response()->json($data);
    }
}
