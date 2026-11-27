<?php

namespace App\Http\Requests;

use App\Enums\ExpenseCategory;
use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'amount' => ['sometimes', 'numeric', 'min:0.01', 'max:999999.99'],
            'category' => ['sometimes', 'string', 'in:'.implode(',', ExpenseCategory::labels())],
        ];
    }

    public function messages(): array
    {
        return [
            'title.string' => 'Title must be a text value.',
            'title.max' => 'Title cannot exceed 255 characters.',
            'amount.numeric' => 'Amount must be a valid number.',
            'amount.min' => 'Amount must be greater than 0.',
            'amount.max' => 'Amount cannot exceed 999,999.99.',
            'category.string' => 'Category must be a text value.',
            'category.in' => 'Invalid category selected. Valid categories are: '.implode(', ', ExpenseCategory::labels()),
        ];
    }
}
