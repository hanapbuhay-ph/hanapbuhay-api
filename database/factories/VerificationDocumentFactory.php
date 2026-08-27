<?php

namespace Database\Factories;

use App\Models\WorkerProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class VerificationDocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'worker_profile_id' => WorkerProfile::factory(),
            'document_type'     => $this->faker->randomElement([
                'government_id',
                'barangay_certificate',
                'selfie_with_id',
                'skill_certificate',
            ]),
            'file_path'         => 'verifications/1/government_id.jpg',
            'status'            => 'pending',
            'remarks'           => null,
        ];
    }
}
