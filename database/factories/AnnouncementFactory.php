<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AnnouncementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'posted_by'  => User::factory()->create(['role' => 'admin'])->id,
            'title'      => fake()->sentence(4),
            'body'       => fake()->paragraph(),
            'expires_at' => fake()->optional()->dateTimeBetween('+1 day', '+30 days'),
            'is_active'  => true,
        ];
    }
}
