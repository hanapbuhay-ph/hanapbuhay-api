<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkerProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'              => User::factory(),
            'bio'                  => null,
            'verification_status'  => 'unverified',
            'trust_tier'           => null,
            'availability_status'  => 'offline',
            'average_rating'       => 0.00,
            'total_reviews'        => 0,
            'completed_jobs'       => 0,
            'verification_remarks' => null,
            'verified_by'          => null,
            'verified_at'          => null,
        ];
    }
}
