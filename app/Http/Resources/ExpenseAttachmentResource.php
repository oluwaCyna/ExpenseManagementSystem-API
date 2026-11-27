<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseAttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'expense_id' => $this->expense_id,
            'filename' => $this->filename,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'file_size_formatted' => $this->getFileSizeFormatted(),
            'description' => $this->description,
            'created_at' => $this->created_at,
            'download_url' => $this->path ? route('attachments.download', ['attachment' => $this->id]) : null,
        ];
    }
}
