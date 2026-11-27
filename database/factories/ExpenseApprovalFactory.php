<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ExpenseApproval>
 */
class ExpenseApprovalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'expense_id' => Expense::factory(),
            'approved_by' => User::factory(),
            'action' => $this->faker->randomElement(['approved', 'rejected', 'resubmitted']),
            'comment' => $this->faker->optional(0.7)->sentence(),
        ];
    }
}
