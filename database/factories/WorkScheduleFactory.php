<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkSchedule>
 */
class WorkScheduleFactory extends Factory
{
    protected $model = WorkSchedule::class;

    public function definition(): array
    {
        return [
            'user_id'  => User::factory(),
            'type'     => 'work',
            'start_at' => now()->startOfDay()->addHours(9),
            'end_at'   => now()->startOfDay()->addHours(18),
            'status'   => 'approved',
        ];
    }
}
