<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WeeklyReport;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WeeklyReport>
 */
class WeeklyReportFactory extends Factory
{
    protected $model = WeeklyReport::class;

    public function definition(): array
    {
        static $offset = 0;
        $weekStart = Carbon::today()->startOfWeek(Carbon::MONDAY)->subWeeks($offset++);

        return [
            'user_id' => User::factory(),
            'week_start_date' => $weekStart->toDateString(),
            'summary' => null,
            'status' => 'draft',
        ];
    }
}
