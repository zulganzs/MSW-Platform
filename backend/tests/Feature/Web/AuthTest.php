<?php

namespace Tests\Feature\Web;

use App\Http\Controllers\Web\AuthController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class WebAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Define mock routes for testing redirect destinations
        Route::get('/login', function () {
            return 'login';
        })->name('login');
        Route::get('/citizen/reports', function () {
            return 'citizen';
        })->name('citizen.reports.index');
        Route::get('/staff/dashboard', function () {
            return 'staff';
        })->name('staff.dashboard');
        Route::get('/crew/dashboard', function () {
            return 'crew';
        })->name('crew.dashboard');
        Route::get('/', function () {
            return 'home';
        });

        // Register the controller routes with web middleware to enable sessions
        Route::middleware('web')->group(function () {
            Route::post('/login', [AuthController::class, 'login']);
            Route::post('/register', [AuthController::class, 'register']);
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/login-view', [AuthController::class, 'showLogin']);
            Route::get('/register-view', [AuthController::class, 'showRegister']);
        });
    }

    public function test_login_success_citizen()
    {
        $user = User::factory()->create([
            'email' => 'citizen@example.com',
            'password' => Hash::make('password'),
            'role' => 'citizen',
        ]);

        $response = $this->post('/login', [
            'email' => 'citizen@example.com',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('citizen.dashboard'));
    }

    public function test_login_success_staff()
    {
        $user = User::factory()->create([
            'email' => 'staff@example.com',
            'password' => Hash::make('password'),
            'role' => 'staff',
        ]);

        $response = $this->post('/login', [
            'email' => 'staff@example.com',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('staff.dashboard'));
    }

    public function test_login_success_crew()
    {
        $user = User::factory()->create([
            'email' => 'crew@example.com',
            'password' => Hash::make('password'),
            'role' => 'crew',
        ]);

        $response = $this->post('/login', [
            'email' => 'crew@example.com',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('crew.dashboard'));
    }

    public function test_login_failure()
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
        ]);

        // Using session middleware to support with('error') and back()
        $response = $this->withSession(['_previous' => ['url' => '/login']])
            ->post('/login', [
                'email' => 'user@example.com',
                'password' => 'wrong-password',
            ]);

        $this->assertGuest();
        $response->assertRedirect('/login');
        $response->assertSessionHas('error', 'Email atau password salah');
    }

    public function test_register_success()
    {
        $response = $this->post('/register', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'role' => 'citizen',
            'name' => 'New User',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('citizen.dashboard'));
    }

    public function test_logout()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect(route('login'));
    }
}
