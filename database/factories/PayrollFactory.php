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
            'base_salary' => 0,
            'bonus' => 0,
            'allowance' => 0,
            'overtime' => 0,
            'deduction' => 0,
            'deduction_socso' => 0,
            'deduction_eis' => 0,
            'deduction_eps' => 0,
            'deduction_pcb' => 0,
            'other_deduction' => 0,
            'net_amount' => 0,
            'currency' => 'MYR',
            'status' => 'draft',
        ];
    }
}
