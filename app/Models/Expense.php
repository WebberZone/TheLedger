<?php

namespace App\Models;

use App\Enums\TransactionType;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;

/**
 * The expenses ledger: Transaction rows scoped to type=expense.
 * The global scope makes Expense::query() invisible to other types,
 * and `creating` stamps the type so callers never set it by hand.
 */
#[UseFactory(ExpenseFactory::class)]
class Expense extends Transaction
{
    protected static function booted(): void
    {
        static::addGlobalScope(
            'type', function (Builder $query) {
                $query->where('transactions.type', TransactionType::Expense);
            }
        );

        static::creating(
            function (Expense $expense) {
                $expense->type ??= TransactionType::Expense;
            }
        );
    }
}
