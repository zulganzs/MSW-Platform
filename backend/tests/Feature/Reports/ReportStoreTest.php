<?php

namespace Tests\Feature\Reports;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportStoreTest extends TestCase
{
    use RefreshDatabase;

    private function getToken(User $user): string
    {
        return $user->createToken('test-device')->plainTextToken;
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'category_id' => Category::factory()->create()->id,
            'description' => 'Tumpukan sampah di pinggir jalan sudah seminggu',
            'latitude' => -6.200000,
            'longitude' => 106.816666,
            'visibility' => 'public',
        ], $overrides);
    }

    public function test_citizen_can_create_report_with_valid_data(): void
    {
        $citizen = User::factory()->citizen()->create();
        $token = $this->getToken($citizen);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/reports', $this->validPayload());

        $response->assertStatus(201)
            ->assertJsonPath('status', 'submitted')
            ->assertJsonPath('visibility', 'public')
            ->assertJsonPath('description', 'Tumpukan sampah di pinggir jalan sudah seminggu')
            ->assertJsonStructure(['id', 'category_id', 'description', 'latitude', 'longitude', 'status', 'visibility', 'created_at', 'updated_at']);

        $this->assertDatabaseHas('reports', [
            'user_id' => $citizen->id,
            'status' => 'submitted',
        ]);
    }

    public function test_missing_category_id_returns_422(): void
    {
        $citizen = User::factory()->citizen()->create();
        $token = $this->getToken($citizen);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/reports', $this->validPayload(['category_id' => null]));

        $response->assertStatus(422);
    }

    public function test_description_shorter_than_10_chars_returns_422(): void
    {
        $citizen = User::factory()->citizen()->create();
        $token = $this->getToken($citizen);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/reports', $this->validPayload(['description' => 'pendek']));

        $response->assertStatus(422);
    }

    public function test_missing_latitude_returns_422(): void
    {
        $citizen = User::factory()->citizen()->create();
        $token = $this->getToken($citizen);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/reports', $this->validPayload(['latitude' => null]));

        $response->assertStatus(422);
    }

    public function test_missing_longitude_returns_422(): void
    {
        $citizen = User::factory()->citizen()->create();
        $token = $this->getToken($citizen);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/reports', $this->validPayload(['longitude' => null]));

        $response->assertStatus(422);
    }

    public function test_invalid_visibility_value_returns_422(): void
    {
        $citizen = User::factory()->citizen()->create();
        $token = $this->getToken($citizen);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/reports', $this->validPayload(['visibility' => 'secret']));

        $response->assertStatus(422);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->postJson('/api/reports', $this->validPayload());

        $response->assertStatus(401);
    }

    public function test_staff_cannot_create_reports(): void
    {
        $staff = User::factory()->staff()->create();
        $token = $this->getToken($staff);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/reports', $this->validPayload());

        $response->assertStatus(403);
    }

    public function test_crew_cannot_create_reports(): void
    {
        $crew = User::factory()->crew()->create();
        $token = $this->getToken($crew);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/reports', $this->validPayload());

        $response->assertStatus(403);
    }

    public function test_anonymous_report_stores_user_id_but_response_strips_identity(): void
    {
        $citizen = User::factory()->citizen()->create();
        $token = $this->getToken($citizen);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/reports', $this->validPayload(['visibility' => 'anonymous']));

        $response->assertStatus(201)
            ->assertJsonPath('visibility', 'anonymous');

        $response->assertJsonMissingPath('user_id');
        $response->assertJsonMissingPath('user');

        $this->assertDatabaseHas('reports', [
            'user_id' => $citizen->id,
            'visibility' => 'anonymous',
        ]);
    }

    public function test_report_is_created_with_status_submitted_not_assignable(): void
    {
        $citizen = User::factory()->citizen()->create();
        $token = $this->getToken($citizen);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/reports', $this->validPayload(['status' => 'completed']));

        $response->assertStatus(201)
            ->assertJsonPath('status', 'submitted');

        $this->assertDatabaseHas('reports', [
            'status' => 'submitted',
        ]);
    }

    public function test_category_must_exist_returns_422_for_bad_id(): void
    {
        $citizen = User::factory()->citizen()->create();
        $token = $this->getToken($citizen);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/reports', $this->validPayload(['category_id' => 99999]));

        $response->assertStatus(422);
    }
}
