<?php

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\Income;

class IncomeFactory extends TransactionFactory
{
    /** @var class-string<Income> */
    protected $model = Income::class;

    public function definition(): array
    {
        return [
            ...parent::definition(),
            'type' => TransactionType::Income,
        ];
    }
}
