<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpenseAttachment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'expense_id',
        'filename',
        'mime_type',
        'file_size',
        'path',
        'disk',
        'description',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }

    public function getFileSizeFormatted(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2).' '.$units[$pow];
    }
}
