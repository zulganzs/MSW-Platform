<?php

namespace Tests\Feature\Crew;

use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrewReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $crew;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = User::factory()->staff()->create();
        $this->crew = User::factory()->crew()->create();
    }

    private function attachCrew(Report $report, ?User $crew = null): void
    {
        $report->crews()->attach($crew->id ?? $this->crew->id, [
            'staff_user_id' => $this->staff->id,
            'assigned_at' => now(),
        ]);
    }

    public function test_crew_sees_only_their_own_assigned_reports(): void
    {
        $own = Report::factory()->assigned()->create();
        $this->attachCrew($own);

        $otherCrew = User::factory()->crew()->create();
        $other = Report::factory()->assigned()->create();
        $this->attachCrew($other, $otherCrew);

        $response = $this->actingAs($this->crew, 'sanctum')
            ->getJson('/api/crew/reports');

        $response->assertStatus(200);
        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($own->id, $ids);
        $this->assertNotContains($other->id, $ids);
    }

    public function test_crew_reports_include_category_and_user_relationships(): void
    {
        $report = Report::factory()->assigned()->create();
        $this->attachCrew($report);

        $response = $this->actingAs($this->crew, 'sanctum')
            ->getJson('/api/crew/reports');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.id', $report->id)
            ->assertJsonPath('data.0.category.name', $report->category->name)
            ->assertJsonPath('data.0.user.id', $report->user->id);
    }

    public function test_crew_reports_paginated_with_15_per_page(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $report = Report::factory()->assigned()->create();
            $this->attachCrew($report);
        }

        $response = $this->actingAs($this->crew, 'sanctum')
            ->getJson('/api/crew/reports');

        $response->assertStatus(200)
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('per_page', 15);
    }

    public function test_anonymous_report_strips_user_fields(): void
    {
        $report = Report::factory()->anonymous()->assigned()->create();
        $this->attachCrew($report);

        $response = $this->actingAs($this->crew, 'sanctum')
            ->getJson('/api/crew/reports');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.visibility', 'anonymous')
            ->assertJsonMissingPath('data.0.user_id')
            ->assertJsonMissingPath('data.0.user');
    }

    public function test_staff_cannot_access_crew_reports_endpoint(): void
    {
        $response = $this->actingAs($this->staff, 'sanctum')
            ->getJson('/api/crew/reports');

        $response->assertStatus(403);
    }

    public function test_citizen_cannot_access_crew_reports_endpoint(): void
    {
        $citizen = User::factory()->citizen()->create();

        $response = $this->actingAs($citizen, 'sanctum')
            ->getJson('/api/crew/reports');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_returns_401(): void
    {
        $response = $this->getJson('/api/crew/reports');

        $response->assertStatus(401);
    }

    public function test_crew_with_no_assignments_returns_empty_list(): void
    {
        $response = $this->actingAs($this->crew, 'sanctum')
            ->getJson('/api/crew/reports');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }
}
