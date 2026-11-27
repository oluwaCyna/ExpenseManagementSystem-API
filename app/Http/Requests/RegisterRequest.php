<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'company_email' => ['required', 'email', 'unique:companies,email'],
            'company_name' => ['required', 'string', 'max:255', 'unique:companies,name'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Your name is required.',
            'name.max' => 'Name cannot exceed 255 characters.',
            'email.required' => 'Email address is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email is already registered.',
            'password.required' => 'Password is required.',
            'password.confirmed' => 'Password confirmation does not match.',
            'company_name.required' => 'Company name is required.',
            'company_name.unique' => 'This company name is already taken.',
            'company_email.required' => 'Company email is required.',
            'company_email.email' => 'Please enter a valid company email address.',
            'company_email.unique' => 'This company email is already registered.',
        ];
    }
}
