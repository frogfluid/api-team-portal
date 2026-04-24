<?php

namespace Database\Factories;

use App\Models\Payroll;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payroll>
 */
class PayrollFactory extends Factory
{
    protected $model = Payroll::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'year_month' => fake()->date('Y-m'),
            'base_salary' => fake()->randomFloat(2, 3000, 8000),
            'status' => 'draft',
        ];
    }
}
