<?php

namespace Database\Factories;

use App\Models\JobScope;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobScope>
 */
class JobScopeFactory extends Factory
{
    protected $model = JobScope::class;

    public function definition(): array
    {
        return [
            'name'                => fake()->unique()->words(2, true),
            'description'         => fake()->sentence(),
            'has_external_output' => false,
        ];
    }
}
