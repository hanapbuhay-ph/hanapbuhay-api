<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'max:255'],
            'email'         => ['required', 'email', 'unique:users,email'],
            'password'      => ['required', 'string', 'min:8', 'confirmed'],
            'mobile_number' => ['required', 'string', 'max:20'],
            'role'          => ['required', 'in:client,worker'],
            'barangay_id'   => ['required', 'exists:barangays,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'          => 'Your full name is required.',
            'name.max'               => 'Name must not exceed 255 characters.',
            'email.required'         => 'An email address is required.',
            'email.email'            => 'Please enter a valid email address.',
            'email.unique'           => 'This email is already registered.',
            'password.required'      => 'A password is required.',
            'password.min'           => 'Password must be at least 8 characters.',
            'password.confirmed'     => 'Password confirmation does not match.',
            'mobile_number.required' => 'A mobile number is required.',
            'mobile_number.max'      => 'Mobile number must not exceed 20 characters.',
            'role.required'          => 'Please select a role (client or worker).',
            'role.in'                => 'Role must be either "client" or "worker".',
            'barangay_id.required'   => 'Please select your barangay.',
            'barangay_id.exists'     => 'The selected barangay is not valid.',
        ];
    }
}
