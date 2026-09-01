<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class HanapbuhayNotificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title'   => fake()->sentence(3),
            'body'    => fake()->sentence(6),
            'type'    => fake()->randomElement([
                'booking_request',
                'booking_accepted',
                'booking_declined',
                'booking_completed',
                'new_message',
                'new_rating',
                'system_announcement',
            ]),
            'data'    => null,
            'is_read' => false,
            'read_at' => null,
        ];
    }

    public function read(): static
    {
        return $this->state([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }
}
