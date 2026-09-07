<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    private const PREDEFINED_NAMES = [
        'Tumpukan Sampah Liar',
        'Tempat Sampah Penuh',
        'Sampah Tidak Terkumpul',
        'Pembuangan B3 Ilegal',
        'Saluran Tersumbat Sampah',
        'Sampah di Taman/Fasilitas Umum',
        'Pembakaran Sampah Sembarangan',
        'Kontainer Sampah Rusak',
        'Sampah Medis Terlantar',
        'Lainnya',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'description' => fake()->sentence(),
            'icon' => null,
        ];
    }

    public function predefined(int $index): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => self::PREDEFINED_NAMES[$index],
        ]);
    }
}
