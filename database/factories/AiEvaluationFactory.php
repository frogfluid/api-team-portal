<?php

namespace Database\Factories;

use App\Models\AiEvaluation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiEvaluation>
 */
class AiEvaluationFactory extends Factory
{
    protected $model = AiEvaluation::class;

    public function definition(): array
    {
        return [
            'user_id'      => User::factory(),
            'evaluator_id' => User::factory(),
            'year_month'   => now()->subMonths(fake()->unique()->numberBetween(0, 240))->format('Y-m'),
            'model'        => 'gpt-4',
            'status'       => 'pending',
            'content'      => fake()->paragraph(),
        ];
    }
}
