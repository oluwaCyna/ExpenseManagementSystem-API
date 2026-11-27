<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'category' => ['required', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Expense title is required.',
            'title.string' => 'Title must be a text value.',
            'title.max' => 'Title cannot exceed 255 characters.',
            'amount.required' => 'Amount is required.',
            'amount.numeric' => 'Amount must be a valid number.',
            'amount.min' => 'Amount must be greater than 0.',
            'amount.max' => 'Amount cannot exceed 999,999.99.',
            'category.required' => 'Category is required.',
            'category.string' => 'Category must be a text value.',
            'category.max' => 'Category cannot exceed 100 characters.',
        ];
    }
}
