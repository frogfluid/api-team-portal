<?php

namespace Database\Factories;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AttendanceRecord>
 */
class AttendanceRecordFactory extends Factory
{
    protected $model = AttendanceRecord::class;

    public function definition(): array
    {
        return [
            'user_id'             => User::factory(),
            'date'                => now()->toDateString(),
            'clock_in_at'         => now()->setTime(9, 0),
            'clock_out_at'        => now()->setTime(18, 0),
            'status'              => 'normal',
            'is_auto_clocked_out' => false,
        ];
    }
}
