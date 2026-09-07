<?php

namespace Tests\Feature\Notifications;

use App\Mail\ReportCompletedMail;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReportCompletedNotificationTest extends TestCase
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

    public function test_sends_mail_to_owner_when_public_report_is_completed(): void
    {
        Mail::fake();

        $owner = User::factory()->citizen()->create();
        $report = Report::factory()->inProgress()->create(['user_id' => $owner->id]);

        $report->status = 'completed';
        $report->save();

        Mail::assertSent(ReportCompletedMail::class, function (ReportCompletedMail $mail) use ($owner) {
            return $mail->hasTo($owner->email)
                && $mail->envelope()->subject === 'Laporan Anda Telah Selesai';
        });
    }

    public function test_anonymous_report_does_not_send_mail(): void
    {
        Mail::fake();

        $owner = User::factory()->citizen()->create();
        $report = Report::factory()->anonymous()->create(['user_id' => $owner->id]);

        $report->status = 'completed';
        $report->save();

        Mail::assertNotSent(ReportCompletedMail::class);
    }

    public function test_update_without_status_change_does_not_send_mail(): void
    {
        Mail::fake();

        $report = Report::factory()->completed()->create();

        // Re-saving an already-completed report must not re-send.
        $report->status = 'completed';
        $report->save();

        // Editing description of an in_progress report must not send either.
        $other = Report::factory()->inProgress()->create();
        $other->update(['description' => 'Sampah menumpuk di pinggir jalan']);

        Mail::assertNothingSent();
    }

    public function test_crew_patch_status_endpoint_sends_mail_to_owner(): void
    {
        Mail::fake();

        $owner = User::factory()->citizen()->create();
        $report = Report::factory()->inProgress()->create(['user_id' => $owner->id]);
        $report->crews()->attach($this->crew->id, [
            'staff_user_id' => $this->staff->id,
            'assigned_at' => now(),
        ]);

        $response = $this->actingAs($this->crew, 'sanctum')
            ->patchJson("/api/crew/reports/{$report->id}/status", [
                'status' => 'completed',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('report.status', 'completed');
        Mail::assertSent(ReportCompletedMail::class, fn (ReportCompletedMail $mail) => $mail->hasTo($owner->email));
    }
}
