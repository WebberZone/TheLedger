<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Models\Income;

class IncomeController extends TransactionController
{
    protected function modelClass(): string
    {
        return Income::class;
    }

    protected function type(): TransactionType
    {
        return TransactionType::Income;
    }

    protected function routePrefix(): string
    {
        return 'income';
    }
}
