<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Models\CashFlow;

class CashFlowController extends TransactionController
{
    protected function modelClass(): string
    {
        return CashFlow::class;
    }

    protected function type(): TransactionType
    {
        return TransactionType::CashFlow;
    }

    protected function routePrefix(): string
    {
        return 'cashflows';
    }
}
