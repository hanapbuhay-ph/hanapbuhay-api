<?php

namespace App\Http\Requests\Message;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message'    => ['required_without:attachment', 'nullable', 'string', 'max:1000'],
            'attachment' => ['required_without:message', 'nullable', 'file', 'mimes:jpeg,jpg,png', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'message.required_without'    => 'A message or attachment is required.',
            'attachment.required_without' => 'A message or attachment is required.',
        ];
    }
}
