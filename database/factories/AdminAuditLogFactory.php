<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AdminAuditLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'admin_id'    => User::factory()->create(['role' => 'admin'])->id,
            'action'      => fake()->randomElement([
                'approve_verification',
                'reject_verification',
                'toggle_active',
                'update_trust_tier',
                'force_cancel_booking',
                'resolve_report',
                'delete_rating',
                'create_category',
                'update_category',
            ]),
            'target_type' => fake()->randomElement(['WorkerProfile', 'User', 'Booking', 'Report', 'RatingReview', 'ServiceCategory']),
            'target_id'   => fake()->numberBetween(1, 100),
            'details'     => null,
            'ip_address'  => fake()->ipv4(),
        ];
    }
}
