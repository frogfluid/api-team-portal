<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WorkDailyLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkDailyLog>
 */
class WorkDailyLogFactory extends Factory
{
    protected $model = WorkDailyLog::class;

    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-30 days', '-1 day')->format('Y-m-d');

        return [
            'user_id'        => User::factory(),
            'work_date'      => $date,
            'started_at'     => $date.' 09:00:00',
            'ended_at'       => $date.' 18:00:00',
            'break_minutes'  => 60,
            'worked_minutes' => 480,
            'status'         => 'submitted',
            'submitted_at'   => now(),
        ];
    }
}
