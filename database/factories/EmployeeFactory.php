<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'user_id'         => User::factory(),
            'employee_no'     => fake()->unique()->numerify('E####'),
            'employment_type' => 'full_time',
            'status'          => 'active',
        ];
    }
}
