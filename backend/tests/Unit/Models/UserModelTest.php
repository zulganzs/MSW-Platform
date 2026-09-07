<?php

namespace Tests\Unit\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_many_reports(): void
    {
        $user = User::factory()->citizen()->create();

        $this->assertInstanceOf(HasMany::class, $user->reports());
    }

    public function test_user_has_many_attachments(): void
    {
        $user = User::factory()->citizen()->create();

        $this->assertInstanceOf(HasMany::class, $user->attachments());
    }

    public function test_user_belongs_to_many_reports_as_crew(): void
    {
        $user = User::factory()->crew()->create();

        $this->assertInstanceOf(BelongsToMany::class, $user->assignedReports());
    }
}
