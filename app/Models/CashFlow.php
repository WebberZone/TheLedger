<?php

namespace App\Models;

use App\Enums\TransactionType;
use Database\Factories\CashFlowFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;

/**
 * The cash-flows ledger (share capital, director loans):
 * Transaction rows scoped to type=cash_flow.
 */
#[UseFactory(CashFlowFactory::class)]
class CashFlow extends Transaction
{
    protected static function booted(): void
    {
        static::addGlobalScope(
            'type', function (Builder $query) {
                $query->where('transactions.type', TransactionType::CashFlow);
            }
        );

        static::creating(
            function (CashFlow $cashFlow) {
                $cashFlow->type ??= TransactionType::CashFlow;
            }
        );
    }
}
