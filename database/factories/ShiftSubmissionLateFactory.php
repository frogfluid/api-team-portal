<?php

namespace Database\Factories;

use App\Models\ShiftSubmissionLate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShiftSubmissionLate>
 */
class ShiftSubmissionLateFactory extends Factory
{
    protected $model = ShiftSubmissionLate::class;

    public function definition(): array
    {
        return [
            'user_id'    => User::factory(),
            'iso_year'   => now()->isoWeekYear,
            'iso_week'   => now()->isoWeek,
            'flagged_at' => now(),
        ];
    }
}
