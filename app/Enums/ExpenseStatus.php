<?php

namespace App\Enums;

enum ExpenseStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Reimbursed = 'reimbursed';

    public static function labels(): array
    {
        return array_map(fn (self $status) => $status->value, self::cases());
    }
}
