<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function valid_register_creates_user_and_returns_token_with_201(): void
    {
        $payload = [
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'password' => 'password123',
            'device_name' => 'iphone-15',
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure(['token', 'user']);

        $this->assertDatabaseHas('users', [
            'email' => 'budi@example.com',
            'name' => 'Budi Santoso',
            'role' => 'citizen',
        ]);
    }

    #[Test]
    public function invalid_email_returns_422(): void
    {
        $payload = [
            'name' => 'Budi',
            'email' => 'not-an-email',
            'password' => 'password123',
            'device_name' => 'iphone-15',
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertStatus(422);
    }

    #[Test]
    public function duplicate_email_returns_422(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $payload = [
            'name' => 'Budi',
            'email' => 'taken@example.com',
            'password' => 'password123',
            'device_name' => 'iphone-15',
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertStatus(422);
    }

    #[Test]
    public function missing_name_returns_422(): void
    {
        $payload = [
            'email' => 'budi@example.com',
            'password' => 'password123',
            'device_name' => 'iphone-15',
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertStatus(422);
    }

    #[Test]
    public function password_too_short_returns_422(): void
    {
        $payload = [
            'name' => 'Budi',
            'email' => 'budi@example.com',
            'password' => 'short',
            'device_name' => 'iphone-15',
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertStatus(422);
    }
}
