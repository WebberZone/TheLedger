<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Models\Expense;

class ExpenseController extends TransactionController
{
    protected function modelClass(): string
    {
        return Expense::class;
    }

    protected function type(): TransactionType
    {
        return TransactionType::Expense;
    }

    protected function routePrefix(): string
    {
        return 'expenses';
    }
}
