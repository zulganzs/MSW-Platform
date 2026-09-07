<?php

namespace Tests\Feature\Reports;

use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportShowTest extends TestCase
{
    use RefreshDatabase;

    private function getToken(User $user): string
    {
        return $user->createToken('test-device')->plainTextToken;
    }

    public function test_can_show_public_report(): void
    {
        $report = Report::factory()->create(['visibility' => 'public']);

        $response = $this->getJson('/api/reports/'.$report->id);

        $response->assertStatus(200)
            ->assertJsonPath('id', $report->id)
            ->assertJsonPath('visibility', 'public');
    }

    public function test_can_show_anonymous_report_with_user_id_stripped(): void
    {
        $reporter = User::factory()->citizen()->create();
        $report = Report::factory()->anonymous()->create([
            'user_id' => null,
        ]);

        $response = $this->getJson('/api/reports/'.$report->id);

        $response->assertStatus(200)
            ->assertJsonPath('visibility', 'anonymous')
            ->assertJsonMissingPath('user_id')
            ->assertJsonMissingPath('user');
    }

    public function test_anonymous_report_with_stored_user_id_strips_identity(): void
    {
        $reporter = User::factory()->citizen()->create();
        $report = Report::factory()->create([
            'visibility' => 'anonymous',
            'user_id' => $reporter->id,
        ]);

        $response = $this->getJson('/api/reports/'.$report->id);

        $response->assertStatus(200)
            ->assertJsonMissingPath('user_id')
            ->assertJsonMissingPath('user');
    }

    public function test_private_report_visible_to_owner(): void
    {
        $owner = User::factory()->citizen()->create();
        $report = Report::factory()->create([
            'visibility' => 'private',
            'user_id' => $owner->id,
        ]);

        $token = $this->getToken($owner);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/reports/'.$report->id);

        $response->assertStatus(200)
            ->assertJsonPath('id', $report->id)
            ->assertJsonPath('visibility', 'private');
    }

    public function test_private_report_returns_403_for_other_citizen(): void
    {
        $owner = User::factory()->citizen()->create();
        $report = Report::factory()->create([
            'visibility' => 'private',
            'user_id' => $owner->id,
        ]);

        $other = User::factory()->citizen()->create();
        $token = $this->getToken($other);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/reports/'.$report->id);

        $response->assertStatus(403);
    }

    public function test_private_report_returns_403_for_unauthenticated(): void
    {
        $owner = User::factory()->citizen()->create();
        $report = Report::factory()->create([
            'visibility' => 'private',
            'user_id' => $owner->id,
        ]);

        $response = $this->getJson('/api/reports/'.$report->id);

        $response->assertStatus(403);
    }

    public function test_private_report_visible_to_staff(): void
    {
        $owner = User::factory()->citizen()->create();
        $report = Report::factory()->create([
            'visibility' => 'private',
            'user_id' => $owner->id,
        ]);

        $staff = User::factory()->staff()->create();
        $token = $this->getToken($staff);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/reports/'.$report->id);

        $response->assertStatus(200);
    }

    public function test_private_report_visible_to_crew(): void
    {
        $owner = User::factory()->citizen()->create();
        $report = Report::factory()->create([
            'visibility' => 'private',
            'user_id' => $owner->id,
        ]);

        $crew = User::factory()->crew()->create();
        $token = $this->getToken($crew);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/reports/'.$report->id);

        $response->assertStatus(200);
    }

    public function test_non_existent_report_returns_404(): void
    {
        $response = $this->getJson('/api/reports/99999');

        $response->assertStatus(404);
    }

    public function test_public_report_includes_user_identity(): void
    {
        $reporter = User::factory()->citizen()->create();
        $report = Report::factory()->create([
            'visibility' => 'public',
            'user_id' => $reporter->id,
        ]);

        $response = $this->getJson('/api/reports/'.$report->id);

        $response->assertStatus(200)
            ->assertJsonPath('user.id', $reporter->id)
            ->assertJsonPath('user.name', $reporter->name);
    }
}
