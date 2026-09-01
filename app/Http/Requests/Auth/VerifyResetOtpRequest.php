<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyResetOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'code'  => ['required', 'string', 'size:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'The email address is required.',
            'email.email'    => 'Please provide a valid email address.',
            'code.required'  => 'The verification code is required.',
            'code.size'      => 'The verification code must be 6 characters.',
        ];
    }
}
