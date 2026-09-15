<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $configuredEmail = env('ADMIN_EMAIL', 'admin@hanapbuhay.com');
        $admin = User::where('email', $configuredEmail)
            ->orWhere('role', 'admin')
            ->orderBy('id')
            ->first();

        $attributes = [
            'name'              => 'HanapBuhay Admin',
            'role'              => 'admin',
            'is_active'         => true,
            'email_verified_at' => now(),
            'mobile_number'     => null,
            'barangay_id'       => null,
            'two_factor_enabled' => true,
        ];

        if ($admin === null) {
            User::create([
                'email'    => $configuredEmail,
                'password' => Hash::make('Admin@1234!'),
                ...$attributes,
            ]);
            return;
        }

        $admin->update($attributes);
    }
}
