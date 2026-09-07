<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function valid_login_returns_token_with_200(): void
    {
        $user = User::factory()->create([
            'email' => 'budi@example.com',
            'password' => 'password123',
        ]);

        $payload = [
            'email' => 'budi@example.com',
            'password' => 'password123',
            'device_name' => 'iphone-15',
        ];

        $response = $this->postJson('/api/login', $payload);

        $response->assertStatus(200)
            ->assertJsonStructure(['token', 'user']);

        $this->assertNotEmpty($response->json('token'));
    }

    #[Test]
    public function wrong_password_returns_401(): void
    {
        User::factory()->create([
            'email' => 'budi@example.com',
            'password' => 'password123',
        ]);

        $payload = [
            'email' => 'budi@example.com',
            'password' => 'wrong-password',
            'device_name' => 'iphone-15',
        ];

        $response = $this->postJson('/api/login', $payload);

        $response->assertStatus(401);
    }

    #[Test]
    public function non_existent_email_returns_401(): void
    {
        $payload = [
            'email' => 'nobody@example.com',
            'password' => 'password123',
            'device_name' => 'iphone-15',
        ];

        $response = $this->postJson('/api/login', $payload);

        $response->assertStatus(401);
    }

    #[Test]
    public function invalid_email_format_returns_422(): void
    {
        $payload = [
            'email' => 'not-an-email',
            'password' => 'password123',
            'device_name' => 'iphone-15',
        ];

        $response = $this->postJson('/api/login', $payload);

        $response->assertStatus(422);
    }
}
