<?php

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\Expense;

class ExpenseFactory extends TransactionFactory
{
    /** @var class-string<Expense> */
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            ...parent::definition(),
            'type' => TransactionType::Expense,
        ];
    }
}
