<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'exists:email_verification_tokens,token'],
            'email' => ['required', 'email'],
        ];
    }

    public function messages(): array
    {
        return [
            'token.required' => 'Verification token is required.',
            'token.exists' => 'Invalid verification token.',
            'email.required' => 'Email is required.',
            'email.email' => 'Please provide a valid email address.',
        ];
    }
}
