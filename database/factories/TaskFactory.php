<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'location' => fake()->optional()->city(),
            'status' => fake()->randomElement(['opened', 'in_progress', 'on_hold', 'blocked', 'done']),
            'priority' => fake()->randomElement(['low', 'normal', 'high', 'urgent']),
            'progress' => fake()->numberBetween(0, 100),
            'due_at' => fake()->optional()->dateTimeBetween('now', '+30 days'),
            'created_by' => User::factory(),
            'owner_id' => User::factory(),
            'last_activity_at' => now(),
        ];
    }
}
