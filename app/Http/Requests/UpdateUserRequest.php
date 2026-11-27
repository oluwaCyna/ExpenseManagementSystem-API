<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => ['required', 'in:Admin,Manager,Employee'],
        ];
    }

    public function messages(): array
    {
        return [
            'role.required' => 'Role is required.',
            'role.in' => 'Role must be one of: Admin, Manager, or Employee.',
        ];
    }
}
