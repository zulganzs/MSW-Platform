<?php

namespace Tests\Feature\Middleware;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckRoleTest extends TestCase
{
    use RefreshDatabase;

    private function getToken(User $user): string
    {
        return $user->createToken('test-device')->plainTextToken;
    }

    public function test_citizen_cannot_access_staff_routes(): void
    {
        $citizen = User::factory()->citizen()->create();
        $token = $this->getToken($citizen);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/staff/dashboard');

        $response->assertStatus(403);
    }

    public function test_staff_can_access_staff_routes(): void
    {
        $staff = User::factory()->staff()->create();
        $token = $this->getToken($staff);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/staff/dashboard');

        $response->assertStatus(200);
    }

    public function test_citizen_cannot_access_crew_routes(): void
    {
        $citizen = User::factory()->citizen()->create();
        $token = $this->getToken($citizen);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/crew/dashboard');

        $response->assertStatus(403);
    }

    public function test_crew_can_access_crew_routes(): void
    {
        $crew = User::factory()->crew()->create();
        $token = $this->getToken($crew);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/crew/dashboard');

        $response->assertStatus(200);
    }

    public function test_staff_cannot_access_crew_routes(): void
    {
        $staff = User::factory()->staff()->create();
        $token = $this->getToken($staff);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/crew/dashboard');

        $response->assertStatus(403);
    }

    public function test_crew_cannot_access_staff_routes(): void
    {
        $crew = User::factory()->crew()->create();
        $token = $this->getToken($crew);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/staff/dashboard');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/api/staff/dashboard');

        $response->assertStatus(401);
    }

    public function test_unauthenticated_request_to_crew_returns_401(): void
    {
        $response = $this->getJson('/api/crew/dashboard');

        $response->assertStatus(401);
    }
}
