<?php

namespace Database\Seeders;

use App\Enums\ExpenseCategory as ExpenseCategoryEnum;
use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => ExpenseCategoryEnum::Travel->value,
                'description' => 'Travel-related expenses including flights, hotels, and transportation',
            ],
            [
                'name' => ExpenseCategoryEnum::Meals->value,
                'description' => 'Meals and food expenses',
            ],
            [
                'name' => ExpenseCategoryEnum::Office->value,
                'description' => 'Office rent, furniture, and infrastructure',
            ],
            [
                'name' => ExpenseCategoryEnum::Technology->value,
                'description' => 'Software, hardware, and IT expenses',
            ],
            [
                'name' => ExpenseCategoryEnum::Training->value,
                'description' => 'Employee training and development',
            ],
            [
                'name' => ExpenseCategoryEnum::Entertainment->value,
                'description' => 'Client entertainment and team building',
            ],
            [
                'name' => ExpenseCategoryEnum::Transportation->value,
                'description' => 'Vehicle expenses, fuel, and parking',
            ],
            [
                'name' => ExpenseCategoryEnum::Utilities->value,
                'description' => 'Electricity, water, internet, and utilities',
            ],
            [
                'name' => ExpenseCategoryEnum::Supplies->value,
                'description' => 'Office supplies and equipment',
            ],
            [
                'name' => ExpenseCategoryEnum::Other->value,
                'description' => 'Other miscellaneous expenses',
            ],
        ];

        foreach ($categories as $category) {
            ExpenseCategory::firstOrCreate(['name' => $category['name']], $category);
        }
    }
}
