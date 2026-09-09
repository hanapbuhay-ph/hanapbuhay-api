<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$email = $argv[1] ?? 'otrebladaro@gmail.com';
$type = $argv[2] ?? 'email_verification'; // Default to email_verification

$code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

DB::table('otp_codes')->insert([
    'email' => $email,
    'code' => $code,
    'type' => $type,
    'expires_at' => now()->addMinutes(10),
    'created_at' => now(),
    'updated_at' => now(),
]);

echo "✅ Fresh OTP code ({$type}) for {$email}: {$code}\n";
echo "Expires at: " . now()->addMinutes(10)->toDateTimeString() . "\n";
