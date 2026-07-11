<?php

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\CashFlow;

class CashFlowFactory extends TransactionFactory
{
    /** @var class-string<CashFlow> */
    protected $model = CashFlow::class;

    public function definition(): array
    {
        return [
            ...parent::definition(),
            'type' => TransactionType::CashFlow,
        ];
    }
}
