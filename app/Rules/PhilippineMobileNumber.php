<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PhilippineMobileNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^09\d{9}$/', $value)) {
            $fail('Enter a valid Philippine mobile number, such as 0917 123 4567.');
        }
    }

    public static function normalize(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $value = preg_replace('/[\s()-]/', '', trim($value));

        if (is_string($value) && preg_match('/^\+639\d{9}$/', $value)) {
            return '0'.substr($value, 3);
        }

        return $value;
    }
}
