<?php

namespace App\Models;

use App\Enums\TransactionType;
use Database\Factories\IncomeFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;

/**
 * The income ledger: Transaction rows scoped to type=income.
 */
#[UseFactory(IncomeFactory::class)]
class Income extends Transaction
{
    protected static function booted(): void
    {
        static::addGlobalScope(
            'type', function (Builder $query) {
                $query->where('transactions.type', TransactionType::Income);
            }
        );

        static::creating(
            function (Income $income) {
                $income->type ??= TransactionType::Income;
            }
        );
    }
}
