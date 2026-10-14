<?php

namespace App\Http\Requests\Admin;

use App\Rules\PhilippineMobileNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdminUserRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('mobile_number')) {
            $this->merge([
                'mobile_number' => PhilippineMobileNumber::normalize($this->input('mobile_number')),
            ]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'mobile_number' => ['required', 'string', new PhilippineMobileNumber],
            'role' => ['required', Rule::in(['client', 'worker', 'admin'])],
            'barangay_id' => ['required', 'integer', 'exists:barangays,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
