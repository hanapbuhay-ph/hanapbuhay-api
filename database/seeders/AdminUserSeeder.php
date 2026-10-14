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
        $superAdmin = User::whereRaw('LOWER(email) = ?', [User::SUPER_ADMIN_EMAIL])->first();
        $superAdmin?->update(['role' => 'super_admin']);

        if (strcasecmp($configuredEmail, User::SUPER_ADMIN_EMAIL) === 0) {
            $configuredEmail = 'admin@hanapbuhay.com';
        }

        $admin = User::where(function ($query) use ($configuredEmail): void {
            $query->where('email', $configuredEmail)->orWhere('role', 'admin');
        })
            ->where('email', '!=', User::SUPER_ADMIN_EMAIL)
            ->orderBy('id')
            ->first();

        $attributes = [
            'name' => 'HanapBuhay Admin',
            'role' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
            'mobile_number' => null,
            'barangay_id' => null,
            'two_factor_enabled' => true,
        ];

        if ($admin === null) {
            User::create([
                'email' => $configuredEmail,
                'password' => Hash::make('Admin@1234!'),
                ...$attributes,
            ]);

            return;
        }

        $admin->update($attributes);
    }
}
