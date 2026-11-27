<?php

namespace App\Enums;

enum ExpenseCategory: string
{
    case Travel = 'Travel';
    case Meals = 'Meals';
    case Office = 'Office';
    case Technology = 'Technology';
    case Training = 'Training';
    case Entertainment = 'Entertainment';
    case Transportation = 'Transportation';
    case Utilities = 'Utilities';
    case Supplies = 'Supplies';
    case Other = 'Other';

    public static function labels(): array
    {
        return [
            self::Travel->value,
            self::Meals->value,
            self::Office->value,
            self::Technology->value,
            self::Training->value,
            self::Entertainment->value,
            self::Transportation->value,
            self::Utilities->value,
            self::Supplies->value,
            self::Other->value,
        ];
    }
}
