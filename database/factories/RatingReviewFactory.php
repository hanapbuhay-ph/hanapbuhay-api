<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RatingReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'rated_by'   => User::factory(),
            'rated_user' => User::factory(),
            'score'      => $this->faker->numberBetween(1, 5),
            'comment'    => $this->faker->optional()->sentence(),
        ];
    }
}
