<?php

namespace Tests\Unit\Models;

use App\Models\Report;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_belongs_to_user(): void
    {
        $report = Report::factory()->create();

        $this->assertInstanceOf(BelongsTo::class, $report->user());
    }

    public function test_report_belongs_to_category(): void
    {
        $report = Report::factory()->create();

        $this->assertInstanceOf(BelongsTo::class, $report->category());
    }

    public function test_report_has_many_attachments(): void
    {
        $report = Report::factory()->create();

        $this->assertInstanceOf(HasMany::class, $report->attachments());
    }

    public function test_report_belongs_to_many_crews(): void
    {
        $report = Report::factory()->create();

        $this->assertInstanceOf(BelongsToMany::class, $report->crews());
    }
}
