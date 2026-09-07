<?php

namespace Tests\Feature\Crew;

use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateStatusTest extends TestCase
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

    private function patchStatus(User $user, array $payload, ?int $reportId = null)
    {
        $id = $reportId ?? 1;

        return $this->actingAs($user, 'sanctum')
            ->patchJson("/api/crew/reports/{$id}/status", $payload);
    }

    public function test_assigned_crew_transitions_assigned_to_in_progress(): void
    {
        $report = Report::factory()->assigned()->create();
        $this->attachCrew($report);

        $response = $this->patchStatus($this->crew, ['status' => 'in_progress'], $report->id);

        $response->assertStatus(200)
            ->assertJsonPath('report.status', 'in_progress');
        $this->assertSame('in_progress', $report->fresh()->status);
    }

    public function test_assigned_crew_transitions_in_progress_to_completed(): void
    {
        $report = Report::factory()->inProgress()->create();
        $this->attachCrew($report);

        $response = $this->patchStatus($this->crew, ['status' => 'completed'], $report->id);

        $response->assertStatus(200)
            ->assertJsonPath('report.status', 'completed');
        $this->assertSame('completed', $report->fresh()->status);
    }

    public function test_skipping_submitted_to_completed_returns_422(): void
    {
        $report = Report::factory()->submitted()->create();
        $this->attachCrew($report);

        $response = $this->patchStatus($this->crew, ['status' => 'completed'], $report->id);

        $response->assertStatus(422);
        $this->assertSame('submitted', $report->fresh()->status);
    }

    public function test_skipping_assigned_to_completed_returns_422(): void
    {
        $report = Report::factory()->assigned()->create();
        $this->attachCrew($report);

        $response = $this->patchStatus($this->crew, ['status' => 'completed'], $report->id);

        $response->assertStatus(422);
        $this->assertSame('assigned', $report->fresh()->status);
    }

    public function test_backwards_in_progress_to_assigned_returns_422(): void
    {
        $report = Report::factory()->inProgress()->create();
        $this->attachCrew($report);

        $response = $this->patchStatus($this->crew, ['status' => 'assigned'], $report->id);

        $response->assertStatus(422);
        $this->assertSame('in_progress', $report->fresh()->status);
    }

    public function test_backwards_completed_to_in_progress_returns_422(): void
    {
        $report = Report::factory()->completed()->create();
        $this->attachCrew($report);

        $response = $this->patchStatus($this->crew, ['status' => 'in_progress'], $report->id);

        $response->assertStatus(422);
        $this->assertSame('completed', $report->fresh()->status);
    }

    public function test_unassigned_crew_returns_403(): void
    {
        $report = Report::factory()->assigned()->create();
        $otherCrew = User::factory()->crew()->create();
        $this->attachCrew($report, $otherCrew);

        $response = $this->patchStatus($this->crew, ['status' => 'in_progress'], $report->id);

        $response->assertStatus(403);
        $this->assertSame('assigned', $report->fresh()->status);
    }

    public function test_staff_returns_403(): void
    {
        $report = Report::factory()->assigned()->create();
        $this->attachCrew($report);

        $response = $this->patchStatus($this->staff, ['status' => 'in_progress'], $report->id);

        $response->assertStatus(403);
    }

    public function test_citizen_returns_403(): void
    {
        $report = Report::factory()->assigned()->create();
        $this->attachCrew($report);
        $citizen = User::factory()->citizen()->create();

        $response = $this->patchStatus($citizen, ['status' => 'in_progress'], $report->id);

        $response->assertStatus(403);
    }

    public function test_unauthenticated_returns_401(): void
    {
        $report = Report::factory()->assigned()->create();

        $response = $this->patchJson("/api/crew/reports/{$report->id}/status", [
            'status' => 'in_progress',
        ]);

        $response->assertStatus(401);
    }

    public function test_invalid_status_value_returns_422(): void
    {
        $report = Report::factory()->assigned()->create();
        $this->attachCrew($report);

        $response = $this->patchStatus($this->crew, ['status' => 'foo'], $report->id);

        $response->assertStatus(422);
        $this->assertSame('assigned', $report->fresh()->status);
    }

    public function test_non_existent_report_returns_404(): void
    {
        $response = $this->patchStatus($this->crew, ['status' => 'in_progress'], 999999);

        $response->assertStatus(404);
    }
}
