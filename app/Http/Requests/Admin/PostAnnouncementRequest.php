<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PostAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'      => ['required', 'string', 'max:200'],
            'body'       => ['required', 'string', 'max:1000'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ];
    }
}
