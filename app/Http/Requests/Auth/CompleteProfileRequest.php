<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class CompleteProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'max:255'],
            'mobile_number' => ['required', 'string', 'max:20'],
            'role'          => ['required', 'in:client,worker'],
            'barangay_id'   => ['required', 'exists:barangays,id'],
            'profile_photo' => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'          => 'Your full name is required.',
            'name.max'               => 'Name must not exceed 255 characters.',
            'mobile_number.required' => 'A mobile number is required.',
            'mobile_number.max'      => 'Mobile number must not exceed 20 characters.',
            'role.required'          => 'Please select a role (client or worker).',
            'role.in'                => 'Role must be either "client" or "worker".',
            'barangay_id.required'   => 'Please select your barangay.',
            'barangay_id.exists'     => 'The selected barangay is not valid.',
            'profile_photo.image'    => 'Profile photo must be an image file.',
            'profile_photo.mimes'    => 'Profile photo must be a JPG, PNG, or WebP file.',
            'profile_photo.max'      => 'Profile photo must not exceed 2MB.',
        ];
    }
}
