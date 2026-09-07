<?php

namespace Tests\Feature\Reports;

use App\Models\Category;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportIndexTest extends TestCase
{
    use RefreshDatabase;

    private function getToken(User $user): string
    {
        return $user->createToken('test-device')->plainTextToken;
    }

    public function test_index_returns_paginated_15_per_page(): void
    {
        Report::factory()->count(20)->create();

        $response = $this->getJson('/api/reports');

        $response->assertStatus(200)
            ->assertJsonPath('per_page', 15)
            ->assertJsonPath('total', 20)
            ->assertJsonPath('current_page', 1);

        $this->assertCount(15, $response->json('data'));
    }

    public function test_index_second_page_returns_remaining_items(): void
    {
        Report::factory()->count(20)->create();

        $response = $this->getJson('/api/reports?page=2');

        $response->assertStatus(200);
        $this->assertCount(5, $response->json('data'));
    }

    public function test_index_filters_by_status(): void
    {
        Report::factory()->submitted()->create();
        Report::factory()->assigned()->create();
        Report::factory()->completed()->create();

        $response = $this->getJson('/api/reports?status=submitted');

        $response->assertStatus(200)
            ->assertJsonPath('total', 1);

        $this->assertSame('submitted', $response->json('data.0.status'));
    }

    public function test_index_filters_by_category_id(): void
    {
        $cat1 = Category::factory()->create();
        $cat2 = Category::factory()->create();

        Report::factory()->create(['category_id' => $cat1->id]);
        Report::factory()->create(['category_id' => $cat1->id]);
        Report::factory()->create(['category_id' => $cat2->id]);

        $response = $this->getJson('/api/reports?category_id='.$cat1->id);

        $response->assertStatus(200)
            ->assertJsonPath('total', 2);

        foreach ($response->json('data') as $item) {
            $this->assertSame($cat1->id, $item['category_id']);
        }
    }

    public function test_index_ordered_by_created_at_desc(): void
    {
        $old = Report::factory()->create(['created_at' => now()->subDays(2)]);
        $new = Report::factory()->create(['created_at' => now()]);
        $mid = Report::factory()->create(['created_at' => now()->subDay()]);

        $response = $this->getJson('/api/reports');

        $response->assertStatus(200);

        $ids = array_column($response->json('data'), 'id');

        $this->assertSame([$new->id, $mid->id, $old->id], $ids);
    }

    public function test_public_reports_visible_to_unauthenticated_users(): void
    {
        Report::factory()->create(['visibility' => 'public']);

        $response = $this->getJson('/api/reports');

        $response->assertStatus(200)
            ->assertJsonPath('total', 1);
    }

    public function test_anonymous_reports_visible_to_unauthenticated_users(): void
    {
        Report::factory()->anonymous()->create();

        $response = $this->getJson('/api/reports');

        $response->assertStatus(200)
            ->assertJsonPath('total', 1);
    }

    public function test_private_reports_not_visible_to_other_citizens_in_index(): void
    {
        $owner = User::factory()->citizen()->create();
        Report::factory()->create([
            'visibility' => 'private',
            'user_id' => $owner->id,
        ]);

        $other = User::factory()->citizen()->create();
        $token = $this->getToken($other);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/reports');

        $response->assertStatus(200)
            ->assertJsonPath('total', 0);
    }

    public function test_private_reports_visible_to_owner_in_index(): void
    {
        $owner = User::factory()->citizen()->create();
        Report::factory()->create([
            'visibility' => 'private',
            'user_id' => $owner->id,
        ]);

        $token = $this->getToken($owner);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/reports');

        $response->assertStatus(200)
            ->assertJsonPath('total', 1);
    }

    public function test_private_reports_visible_to_staff_in_index(): void
    {
        $owner = User::factory()->citizen()->create();
        Report::factory()->create([
            'visibility' => 'private',
            'user_id' => $owner->id,
        ]);

        $staff = User::factory()->staff()->create();
        $token = $this->getToken($staff);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/reports');

        $response->assertStatus(200)
            ->assertJsonPath('total', 1);
    }

    public function test_private_reports_visible_to_crew_in_index(): void
    {
        $owner = User::factory()->citizen()->create();
        Report::factory()->create([
            'visibility' => 'private',
            'user_id' => $owner->id,
        ]);

        $crew = User::factory()->crew()->create();
        $token = $this->getToken($crew);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/reports');

        $response->assertStatus(200)
            ->assertJsonPath('total', 1);
    }

    public function test_anonymous_reports_strip_user_id_and_user_name_from_index(): void
    {
        $reporter = User::factory()->citizen()->create();
        Report::factory()->anonymous()->create([
            'user_id' => null,
        ]);

        $response = $this->getJson('/api/reports');

        $response->assertStatus(200);

        $firstItem = $response->json('data.0');

        $this->assertArrayNotHasKey('user_id', $firstItem);
        $this->assertArrayNotHasKey('user', $firstItem);
    }

    public function test_unauthenticated_sees_public_and_anonymous_but_not_private(): void
    {
        Report::factory()->create(['visibility' => 'public']);
        Report::factory()->anonymous()->create();
        $owner = User::factory()->citizen()->create();
        Report::factory()->create([
            'visibility' => 'private',
            'user_id' => $owner->id,
        ]);

        $response = $this->getJson('/api/reports');

        $response->assertStatus(200)
            ->assertJsonPath('total', 2);

        $visibilities = array_column($response->json('data'), 'visibility');
        $this->assertContains('public', $visibilities);
        $this->assertContains('anonymous', $visibilities);
        $this->assertNotContains('private', $visibilities);
    }

    public function test_combined_status_and_category_filter(): void
    {
        $cat = Category::factory()->create();

        Report::factory()->create(['category_id' => $cat->id, 'status' => 'submitted']);
        Report::factory()->create(['category_id' => $cat->id, 'status' => 'assigned']);
        Report::factory()->create(['status' => 'submitted']);

        $response = $this->getJson('/api/reports?status=submitted&category_id='.$cat->id);

        $response->assertStatus(200)
            ->assertJsonPath('total', 1);
    }
}
