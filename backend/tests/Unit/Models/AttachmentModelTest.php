<?php

namespace Tests\Unit\Models;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttachmentModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_attachment_belongs_to_report(): void
    {
        $attachment = Attachment::factory()->create();

        $this->assertInstanceOf(BelongsTo::class, $attachment->report());
    }

    public function test_attachment_belongs_to_user(): void
    {
        $attachment = Attachment::factory()->create();

        $this->assertInstanceOf(BelongsTo::class, $attachment->user());
    }
}
