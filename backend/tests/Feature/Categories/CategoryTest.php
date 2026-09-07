<?php

namespace Tests\Feature\Categories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    private function getToken(User $user): string
    {
        return $user->createToken('test-device')->plainTextToken;
    }

    private function staffToken(): string
    {
        return $this->getToken(User::factory()->staff()->create());
    }

    private function citizenToken(): string
    {
        return $this->getToken(User::factory()->citizen()->create());
    }

    private function crewToken(): string
    {
        return $this->getToken(User::factory()->crew()->create());
    }

    // ---------- Public index ----------

    public function test_index_returns_200_with_array_without_auth(): void
    {
        $response = $this->getJson('/api/categories');

        $response->assertStatus(200)
            ->assertJsonIsArray();
    }

    public function test_index_returns_empty_array_when_no_categories(): void
    {
        $response = $this->getJson('/api/categories');

        $response->assertStatus(200)
            ->assertExactJson([]);
    }

    public function test_index_returns_categories_when_they_exist(): void
    {
        Category::factory()->count(3)->create();

        $response = $this->getJson('/api/categories');

        $response->assertStatus(200)
            ->assertJsonCount(3)
            ->assertJsonStructure([
                '*' => ['id', 'name', 'description', 'icon'],
            ]);
    }

    // ---------- Store (staff only) ----------

    public function test_staff_can_create_category(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->staffToken())
            ->postJson('/api/staff/categories', [
                'name' => 'Tumpukan Sampah Liar',
                'description' => 'Sampah menumpuk di lokasi terlarang',
                'icon' => 'trash',
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Tumpukan Sampah Liar']);
        $this->assertDatabaseHas('categories', ['name' => 'Tumpukan Sampah Liar']);
    }

    public function test_citizen_cannot_create_category(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->citizenToken())
            ->postJson('/api/staff/categories', [
                'name' => 'Test Category',
            ]);

        $response->assertStatus(403);
    }

    public function test_crew_cannot_create_category(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->crewToken())
            ->postJson('/api/staff/categories', [
                'name' => 'Test Category',
            ]);

        $response->assertStatus(403);
    }

    public function test_unauthenticated_cannot_create_category(): void
    {
        $response = $this->postJson('/api/staff/categories', [
            'name' => 'Test Category',
        ]);

        $response->assertStatus(401);
    }

    public function test_store_missing_name_returns_422(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->staffToken())
            ->postJson('/api/staff/categories', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_duplicate_name_returns_422(): void
    {
        Category::factory()->create(['name' => 'Existing Category']);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->staffToken())
            ->postJson('/api/staff/categories', [
                'name' => 'Existing Category',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    // ---------- Update (staff only) ----------

    public function test_staff_can_update_category(): void
    {
        $category = Category::factory()->create(['name' => 'Old Name']);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->staffToken())
            ->putJson("/api/staff/categories/{$category->id}", [
                'name' => 'New Name',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'New Name']);
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'New Name']);
    }

    public function test_citizen_cannot_update_category(): void
    {
        $category = Category::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->citizenToken())
            ->putJson("/api/staff/categories/{$category->id}", [
                'name' => 'Hacked',
            ]);

        $response->assertStatus(403);
    }

    // ---------- Destroy (staff only) ----------

    public function test_staff_can_delete_category(): void
    {
        $category = Category::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->staffToken())
            ->deleteJson("/api/staff/categories/{$category->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_citizen_cannot_delete_category(): void
    {
        $category = Category::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->citizenToken())
            ->deleteJson("/api/staff/categories/{$category->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }
}
