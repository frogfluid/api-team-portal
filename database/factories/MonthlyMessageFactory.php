<?php

namespace Database\Factories;

use App\Models\MonthlyMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MonthlyMessage>
 */
class MonthlyMessageFactory extends Factory
{
    protected $model = MonthlyMessage::class;

    public function definition(): array
    {
        return [
            'user_id'      => User::factory(),
            'author_id'    => User::factory(),
            'target_month' => now()->startOfMonth()->toDateString(),
            'review'       => fake()->paragraph(),
            'goals'        => ['ship parity'],
        ];
    }
}
