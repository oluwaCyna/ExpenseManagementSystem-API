<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'attachment' => ['required', 'file', 'mimes:jpeg,png,jpg,pdf,doc,docx', 'max:5120'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'attachment.required' => 'File is required.',
            'attachment.file' => 'Uploaded content must be a valid file.',
            'attachment.mimes' => 'File must be an image (JPEG, PNG, JPG) or document (PDF, DOC, DOCX).',
            'attachment.max' => 'File size cannot exceed 5MB.',
            'description.string' => 'Description must be text.',
            'description.max' => 'Description cannot exceed 500 characters.',
        ];
    }
}
