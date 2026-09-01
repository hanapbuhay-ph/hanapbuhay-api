<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // current_password is optional for Google-only accounts
            // that are setting a password for the first time
            'current_password' => ['sometimes', 'nullable', 'string'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
