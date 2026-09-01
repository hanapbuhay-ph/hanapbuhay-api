<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class BarangayFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'      => $this->faker->city(),
            'latitude'  => $this->faker->latitude(10.0, 11.0),
            'longitude' => $this->faker->longitude(124.0, 125.0),
            'is_active' => true,
        ];
    }
}
