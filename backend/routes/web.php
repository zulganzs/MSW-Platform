<?php

use App\Http\Controllers\Web;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }
    $role = auth()->user()->role;

    return redirect()->route(match ($role) {
        'citizen' => 'citizen.dashboard',
        'staff' => 'staff.dashboard',
        'crew' => 'crew.dashboard',
        default => 'login',
    });
});

Route::get('/login', [Web\AuthController::class, 'showLogin'])->name('login')->middleware('guest');
Route::post('/login', [Web\AuthController::class, 'login'])->middleware('guest');
Route::get('/register', [Web\AuthController::class, 'showRegister'])->name('register')->middleware('guest');
Route::post('/register', [Web\AuthController::class, 'register'])->middleware('guest');
Route::post('/logout', [Web\AuthController::class, 'logout'])->name('logout')->middleware('auth');

Route::middleware(['auth', 'auth.session', 'role:citizen'])->prefix('citizen')->name('citizen.')->group(function () {
    Route::get('/dashboard', [Web\CitizenController::class, 'dashboard'])->name('dashboard');
    Route::get('/reports', [Web\CitizenController::class, 'reportList'])->name('reports.index');
    Route::get('/reports/create', [Web\CitizenController::class, 'reportCreate'])->name('reports.create');
    Route::post('/reports', [Web\CitizenController::class, 'reportStore'])->name('reports.store');
    Route::get('/reports/{report}', [Web\CitizenController::class, 'reportShow'])->name('reports.show');
    Route::delete('/reports/{report}', [Web\CitizenController::class, 'reportDestroy'])->name('reports.destroy');
    Route::get('/map', [Web\CitizenController::class, 'map'])->name('map');
});

Route::middleware(['auth', 'auth.session', 'role:staff'])->prefix('staff')->name('staff.')->group(function () {
    Route::get('/dashboard', [Web\StaffController::class, 'dashboard'])->name('dashboard');
    Route::post('/reports/{report}/assign', [Web\StaffController::class, 'assignCrew'])->name('reports.assign');
});

Route::middleware(['auth', 'auth.session', 'role:crew'])->prefix('crew')->name('crew.')->group(function () {
    Route::get('/dashboard', [Web\CrewController::class, 'dashboard'])->name('dashboard');
    Route::get('/reports/{report}', [Web\CrewController::class, 'reportDetail'])->name('reports.show');
    Route::patch('/reports/{report}/status', [Web\CrewController::class, 'updateStatus'])->name('reports.status');
    Route::post('/reports/{report}/closure', [Web\CrewController::class, 'uploadClosure'])->name('reports.closure');
});
