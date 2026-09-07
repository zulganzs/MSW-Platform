<?php

use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StatusController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});

// Public category listing
Route::get('/categories', [CategoryController::class, 'index']);

// Staff routes (staff only)
Route::middleware(['auth:sanctum', 'role:staff'])->prefix('staff')->group(function () {
    Route::get('/dashboard', fn () => response()->json(['status' => 'ok']));
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{category}', [CategoryController::class, 'update']);
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);
    Route::post('/reports/{report}/assign', [AssignmentController::class, 'assign']);
    Route::get('/users', [UserController::class, 'index']);
});

// Crew routes (crew only)
Route::middleware(['auth:sanctum', 'role:crew'])->prefix('crew')->group(function () {
    Route::get('/dashboard', fn () => response()->json(['status' => 'ok']));
    Route::get('/reports', [ReportController::class, 'crewReports']);
    Route::patch('/reports/{report}/status', [StatusController::class, 'updateStatus']);
});

// Attachments (auth required; type-based role check in controller)
Route::middleware('auth:sanctum')->post('/attachments', [AttachmentController::class, 'store']);

// Public report access (index + show for public/anonymous reports)
Route::get('/reports', [ReportController::class, 'index']);
Route::get('/reports/{report}', [ReportController::class, 'show']);

// Citizen-only report actions
Route::middleware(['auth:sanctum', 'role:citizen'])->group(function () {
    Route::post('/reports', [ReportController::class, 'store']);
    Route::delete('/reports/{report}', [ReportController::class, 'destroy']);
});
