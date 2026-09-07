<?php

namespace Tests\Feature\Attachments;

use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AttachmentUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Metis #4: never hit real disk during tests.
        Storage::fake('public');
    }

    private function getToken(User $user): string
    {
        return $user->createToken('test-device')->plainTextToken;
    }

    private function imageFile(): UploadedFile
    {
        return UploadedFile::fake()->image('photo.jpg');
    }

    private function attachCrew(Report $report, User $crew, User $staff): void
    {
        $report->crews()->attach($crew->id, [
            'staff_user_id' => $staff->id,
            'assigned_at' => now(),
        ]);
    }

    // 1. Citizen uploads submission attachment on own report (status=submitted) -> 201
    #[Test]
    public function citizen_uploads_submission_attachment_on_own_report(): void
    {
        $citizen = User::factory()->citizen()->create();
        $report = Report::factory()->submitted()->create(['user_id' => $citizen->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->getToken($citizen))
            ->postJson('/api/attachments', [
                'report_id' => $report->id,
                'file' => $this->imageFile(),
                'type' => 'submission',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'report_id', 'user_id', 'file_path', 'type'])
            ->assertJsonFragment([
                'report_id' => $report->id,
                'user_id' => $citizen->id,
                'type' => 'submission',
            ]);

        $this->assertStringStartsWith('attachments/', $response->json('file_path'));
        $this->assertDatabaseHas('attachments', [
            'report_id' => $report->id,
            'user_id' => $citizen->id,
            'type' => 'submission',
        ]);
        // File actually stored on the faked disk.
        Storage::disk('public')->assertExists($response->json('file_path'));
    }

    // 2. Citizen uploads closure attachment -> 403 (closure is crew-only)
    #[Test]
    public function citizen_cannot_upload_closure_attachment(): void
    {
        $citizen = User::factory()->citizen()->create();
        $report = Report::factory()->inProgress()->create(['user_id' => $citizen->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->getToken($citizen))
            ->postJson('/api/attachments', [
                'report_id' => $report->id,
                'file' => $this->imageFile(),
                'type' => 'closure',
            ]);

        $response->assertStatus(403);
    }

    // 3. Crew uploads submission attachment -> 403 (submission is citizen-only)
    #[Test]
    public function crew_cannot_upload_submission_attachment(): void
    {
        $crew = User::factory()->crew()->create();
        $staff = User::factory()->staff()->create();
        $report = Report::factory()->submitted()->create();
        $this->attachCrew($report, $crew, $staff);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->getToken($crew))
            ->postJson('/api/attachments', [
                'report_id' => $report->id,
                'file' => $this->imageFile(),
                'type' => 'submission',
            ]);

        $response->assertStatus(403);
    }

    // 4. Crew uploads closure attachment on assigned report (status=in_progress) -> 201
    #[Test]
    public function crew_uploads_closure_attachment_on_assigned_report(): void
    {
        $crew = User::factory()->crew()->create();
        $staff = User::factory()->staff()->create();
        $report = Report::factory()->inProgress()->create();
        $this->attachCrew($report, $crew, $staff);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->getToken($crew))
            ->postJson('/api/attachments', [
                'report_id' => $report->id,
                'file' => $this->imageFile(),
                'type' => 'closure',
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'report_id' => $report->id,
                'user_id' => $crew->id,
                'type' => 'closure',
            ]);

        $this->assertStringStartsWith('attachments/', $response->json('file_path'));
        $this->assertDatabaseHas('attachments', [
            'report_id' => $report->id,
            'user_id' => $crew->id,
            'type' => 'closure',
        ]);
        Storage::disk('public')->assertExists($response->json('file_path'));
    }

    // 5. Crew uploads closure on report NOT assigned to them -> 403
    #[Test]
    public function crew_cannot_upload_closure_on_unassigned_report(): void
    {
        $crew = User::factory()->crew()->create();
        $assignedCrew = User::factory()->crew()->create();
        $staff = User::factory()->staff()->create();
        $report = Report::factory()->inProgress()->create();
        $this->attachCrew($report, $assignedCrew, $staff);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->getToken($crew))
            ->postJson('/api/attachments', [
                'report_id' => $report->id,
                'file' => $this->imageFile(),
                'type' => 'closure',
            ]);

        $response->assertStatus(403);
    }

    // 6. Non-image file upload -> 422
    #[Test]
    public function non_image_file_returns_422(): void
    {
        $citizen = User::factory()->citizen()->create();
        $report = Report::factory()->submitted()->create(['user_id' => $citizen->id]);

        $pdf = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->withHeader('Authorization', 'Bearer '.$this->getToken($citizen))
            ->postJson('/api/attachments', [
                'report_id' => $report->id,
                'file' => $pdf,
                'type' => 'submission',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    // 7. File > 5MB -> 422 (max:5120 in KB)
    #[Test]
    public function file_exceeding_5mb_returns_422(): void
    {
        $citizen = User::factory()->citizen()->create();
        $report = Report::factory()->submitted()->create(['user_id' => $citizen->id]);

        $big = UploadedFile::fake()->image('big.jpg')->size(6000);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->getToken($citizen))
            ->postJson('/api/attachments', [
                'report_id' => $report->id,
                'file' => $big,
                'type' => 'submission',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    // 8. Unauthenticated upload -> 401
    #[Test]
    public function unauthenticated_upload_returns_401(): void
    {
        $report = Report::factory()->submitted()->create();

        $response = $this->postJson('/api/attachments', [
            'report_id' => $report->id,
            'file' => $this->imageFile(),
            'type' => 'submission',
        ]);

        $response->assertStatus(401);
    }

    // 9. Upload on non-existent report_id -> 422 (exists:reports,id validation)
    #[Test]
    public function non_existent_report_id_returns_422(): void
    {
        $citizen = User::factory()->citizen()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->getToken($citizen))
            ->postJson('/api/attachments', [
                'report_id' => 999999,
                'file' => $this->imageFile(),
                'type' => 'submission',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['report_id']);
    }

    // 10. Citizen uploads on someone else's report -> 403
    #[Test]
    public function citizen_cannot_upload_on_other_citizens_report(): void
    {
        $owner = User::factory()->citizen()->create();
        $other = User::factory()->citizen()->create();
        $report = Report::factory()->submitted()->create(['user_id' => $owner->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->getToken($other))
            ->postJson('/api/attachments', [
                'report_id' => $report->id,
                'file' => $this->imageFile(),
                'type' => 'submission',
            ]);

        $response->assertStatus(403);
    }
}
