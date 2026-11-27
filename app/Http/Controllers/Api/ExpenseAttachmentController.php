<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExpenseAttachmentRequest;
use App\Http\Resources\ExpenseAttachmentResource;
use App\Models\Expense;
use App\Models\ExpenseAttachment;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Storage;

class ExpenseAttachmentController extends Controller
{
    use ApiResponse;

    public function index(Expense $expense)
    {
        $this->authorize('view', $expense);

        $attachments = $expense->attachments()->latest()->get();

        return ExpenseAttachmentResource::collection($attachments);
    }

    public function store(StoreExpenseAttachmentRequest $request, Expense $expense)
    {
        $this->authorize('update', $expense);

        $validated = $request->validated();
        $file = $validated['attachment'];

        $path = $file->store("expenses/{$expense->id}", 'public');

        $attachment = ExpenseAttachment::create([
            'expense_id' => $expense->id,
            'filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'path' => $path,
            'disk' => 'public',
            'description' => $validated['description'] ?? null,
        ]);

        return $this->success(
            new ExpenseAttachmentResource($attachment),
            'Attachment uploaded successfully',
            201
        );
    }

    public function destroy(Expense $expense, ExpenseAttachment $attachment)
    {
        $this->authorize('update', $expense);

        if ($attachment->expense_id !== $expense->id) {
            return $this->error('Attachment not found for this expense', 404);
        }

        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();

        return $this->success(null, 'Attachment deleted successfully');
    }

    public function download(ExpenseAttachment $attachment)
    {
        $this->authorize('view', $attachment->expense);

        if (! Storage::disk($attachment->disk)->exists($attachment->path)) {
            return $this->error('File not found', 404);
        }

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->filename
        );
    }
}
