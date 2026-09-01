<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'       => ['required', 'email'],
            'reset_token' => ['required', 'string'],
            'password'    => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required'              => 'The email address is required.',
            'email.email'                 => 'Please provide a valid email address.',
            'reset_token.required'        => 'The reset token is required.',
            'password.required'           => 'A new password is required.',
            'password.min'                => 'The password must be at least 8 characters.',
            'password.confirmed'          => 'The password confirmation does not match.',
        ];
    }
}
