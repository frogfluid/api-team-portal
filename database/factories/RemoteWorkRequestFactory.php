<?php

namespace Database\Factories;

use App\Models\RemoteWorkRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RemoteWorkRequest>
 */
class RemoteWorkRequestFactory extends Factory
{
    protected $model = RemoteWorkRequest::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('+1 day', '+7 days');
        $end   = fake()->dateTimeBetween($start, '+14 days');

        return [
            'user_id'          => User::factory(),
            'region'           => 'domestic',
            'start_date'       => $start->format('Y-m-d'),
            'end_date'         => $end->format('Y-m-d'),
            'reason'           => fake()->sentence(8),
            'deliverables'     => fake()->paragraph(2),
            'work_environment' => fake()->sentence(10),
            'status'           => 'pending',
        ];
    }
}
