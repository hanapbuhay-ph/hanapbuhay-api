<?php

namespace App\Http\Requests\Worker;

use Illuminate\Foundation\Http\FormRequest;

class SubmitVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'government_id_front'  => ['required', 'file', 'image', 'max:5120'],
            'government_id_back'   => ['required', 'file', 'image', 'max:5120'],
            'barangay_certificate' => ['required', 'file', 'image', 'max:5120'],
            'selfie_with_id'       => ['required', 'file', 'image', 'max:5120'],
            'skill_certificate'    => ['nullable', 'file', 'image', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'government_id_front.required'  => 'The front of your government-issued ID is required.',
            'government_id_front.image'     => 'The front of your government-issued ID must be an image file.',
            'government_id_front.max'       => 'The front of your government-issued ID must not exceed 5MB.',
            'government_id_back.required'   => 'The back of your government-issued ID is required.',
            'government_id_back.image'      => 'The back of your government-issued ID must be an image file.',
            'government_id_back.max'        => 'The back of your government-issued ID must not exceed 5MB.',
            'barangay_certificate.required' => 'A barangay certificate is required.',
            'barangay_certificate.image'    => 'Barangay certificate must be an image file.',
            'barangay_certificate.max'      => 'Barangay certificate must not exceed 5MB.',
            'selfie_with_id.required'       => 'A selfie with ID is required.',
            'selfie_with_id.image'          => 'Selfie with ID must be an image file.',
            'selfie_with_id.max'            => 'Selfie with ID must not exceed 5MB.',
            'skill_certificate.image'       => 'Skill certificate must be an image file.',
            'skill_certificate.max'         => 'Skill certificate must not exceed 5MB.',
        ];
    }
}
