<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTrustTierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'trust_tier' => ['required', 'string', 'in:verified,trusted,flagged,revoked'],
            'remarks'    => ['required', 'string', 'max:1000'],
        ];
    }
}
