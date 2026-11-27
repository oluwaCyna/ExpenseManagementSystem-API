<?php

namespace Database\Factories;

use App\Models\Expense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ExpenseAttachment>
 */
class ExpenseAttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'expense_id' => Expense::factory(),
            'filename' => $this->faker->word().'.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => $this->faker->numberBetween(1024, 5120000),
            'path' => 'expenses/1/'.$this->faker->word().'.pdf',
            'disk' => 'public',
            'description' => $this->faker->optional()->sentence(),
        ];
    }
}
