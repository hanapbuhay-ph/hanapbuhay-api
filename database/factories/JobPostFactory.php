<?php

namespace Database\Factories;

use App\Models\ServiceCategory;
use App\Models\WorkerProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class JobPostFactory extends Factory
{
    public function definition(): array
    {
        return [
            'worker_profile_id'   => WorkerProfile::factory(),
            'service_category_id' => ServiceCategory::factory(),
            'title'               => fake()->sentence(4),
            'description'         => fake()->paragraph(),
            'rate_amount'         => fake()->randomFloat(2, 100, 2000),
            'rate_type'           => fake()->randomElement([
                'hourly', 'daily', 'weekly', 'monthly', 'per_session', 'per_project',
            ]),
            'is_available'        => true,
            'is_active'           => true,
        ];
    }
}
