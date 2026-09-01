<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@hanapbuhay.com'],
            [
                'name'              => 'HanapBuhay Admin',
                'password'          => Hash::make('Admin@1234!'),
                'role'              => 'admin',
                'is_active'         => true,
                'email_verified_at' => now(),
                'mobile_number'     => null,
                'barangay_id'       => null,
            ]
        );
    }
}
