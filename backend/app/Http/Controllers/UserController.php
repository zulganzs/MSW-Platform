<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query()->select(['id', 'name', 'email']);

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        return response()->json($query->get());
    }
}
