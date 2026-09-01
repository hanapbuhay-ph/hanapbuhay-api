<?php

namespace Database\Factories;

use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingFactory extends Factory
{
    public function definition(): array
    {
        static $sequence = 0;
        $sequence++;

        return [
            'booking_code'        => sprintf('HB-%d-%05d', now()->year, $sequence),
            'client_id'           => User::factory(),
            'worker_id'           => User::factory(),
            'service_category_id' => ServiceCategory::factory(),
            'notes'               => null,
            'scheduled_at'        => now()->addDay(),
            'status'              => 'completed',
        ];
    }
}
