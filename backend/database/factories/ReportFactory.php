<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'user_id' => User::factory(),
            'description' => fake()->sentence(8),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'status' => 'submitted',
            'visibility' => 'public',
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'submitted']);
    }

    public function assigned(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'assigned']);
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'in_progress']);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'completed']);
    }

    public function anonymous(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'visibility' => 'anonymous',
        ]);
    }
}
