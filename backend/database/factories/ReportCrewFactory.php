<?php

namespace Database\Factories;

use App\Models\Report;
use App\Models\ReportCrew;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportCrew>
 */
class ReportCrewFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'report_id' => Report::factory(),
            'crew_user_id' => User::factory()->crew(),
            'staff_user_id' => User::factory()->staff(),
            'assigned_at' => now(),
        ];
    }
}
