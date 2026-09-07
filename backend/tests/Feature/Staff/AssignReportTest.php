<?php

namespace Tests\Feature\Staff;

use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AssignReportTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Report $report;

    private User $crew;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = User::factory()->staff()->create();
        $this->report = Report::factory()->submitted()->create();
        $this->crew = User::factory()->crew()->create();
    }

    private function assignAs(User $user, array $payload, ?int $reportId = null)
    {
        $token = $user->createToken('test-device')->plainTextToken;
        $id = $reportId ?? $this->report->id;

        return $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/staff/reports/{$id}/assign", $payload);
    }

    public function test_staff_assigns_crew_to_submitted_report(): void
    {
        $response = $this->assignAs($this->staff, ['crew_user_id' => $this->crew->id]);

        $response->assertStatus(200)
            ->assertJsonPath('report.status', 'assigned')
            ->assertJsonPath('message', 'Kru berhasil ditugaskan.');

        $this->assertDatabaseHas('report_crew', [
            'report_id' => $this->report->id,
            'crew_user_id' => $this->crew->id,
            'staff_user_id' => $this->staff->id,
        ]);

        $pivot = DB::table('report_crew')
            ->where('report_id', $this->report->id)
            ->where('crew_user_id', $this->crew->id)
            ->first();
        $this->assertNotNull($pivot->assigned_at);

        $this->assertSame('assigned', $this->report->fresh()->status);
    }

    public function test_assigning_user_with_non_crew_role_returns_422(): void
    {
        $citizen = User::factory()->citizen()->create();

        $response = $this->assignAs($this->staff, ['crew_user_id' => $citizen->id]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('report_crew', ['report_id' => $this->report->id]);
    }

    public function test_assigning_staff_user_as_crew_returns_422(): void
    {
        $otherStaff = User::factory()->staff()->create();

        $response = $this->assignAs($this->staff, ['crew_user_id' => $otherStaff->id]);

        $response->assertStatus(422);
    }

    public function test_double_assignment_returns_409(): void
    {
        $this->report->crews()->attach($this->crew->id, [
            'staff_user_id' => $this->staff->id,
            'assigned_at' => now(),
        ]);

        $response = $this->assignAs($this->staff, ['crew_user_id' => $this->crew->id]);

        $response->assertStatus(409)
            ->assertJsonPath('message', 'Kru sudah ditugaskan ke laporan ini.');

        $this->assertDatabaseCount('report_crew', 1);
    }

    public function test_citizen_token_returns_403(): void
    {
        $citizen = User::factory()->citizen()->create();

        $response = $this->assignAs($citizen, ['crew_user_id' => $this->crew->id]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('report_crew', ['report_id' => $this->report->id]);
    }

    public function test_crew_token_returns_403(): void
    {
        $response = $this->assignAs($this->crew, ['crew_user_id' => $this->crew->id]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('report_crew', ['report_id' => $this->report->id]);
    }

    public function test_unauthenticated_returns_401(): void
    {
        $response = $this->postJson("/api/staff/reports/{$this->report->id}/assign", [
            'crew_user_id' => $this->crew->id,
        ]);

        $response->assertStatus(401);
    }

    public function test_non_existent_crew_user_id_returns_422(): void
    {
        $response = $this->assignAs($this->staff, ['crew_user_id' => 999999]);

        $response->assertStatus(422);
    }

    public function test_non_existent_report_returns_404(): void
    {
        $response = $this->assignAs($this->staff, ['crew_user_id' => $this->crew->id], 999999);

        $response->assertStatus(404);
    }
}
