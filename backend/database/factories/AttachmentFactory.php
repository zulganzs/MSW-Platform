<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     *
     * ponytail: file_path is relative to storage/app/public/ — Storage::fake()
     * replaces the public disk with a temp one; never use a real disk path here.
     */
    public function definition(): array
    {
        return [
            'report_id' => Report::factory(),
            'user_id' => User::factory(),
            'file_path' => 'attachments/'.$this->faker->uuid.'.jpg',
            'type' => 'submission',
        ];
    }

    public function closure(): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'closure']);
    }
}
