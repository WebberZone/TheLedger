<?php

namespace App\Enums;

enum TransactionType: string
{
    case Expense = 'expense';
    case Income = 'income';
    case CashFlow = 'cash_flow';

    /**
     * What the "other party" column is called for this ledger —
     * mirrors Vendor/Customer in the old tracker spreadsheet.
     */
    public function counterpartyLabel(): string
    {
        return match ($this) {
            self::Expense => 'Supplier',
            self::Income => 'Customer',
            self::CashFlow => 'Source',
        };
    }

    public function pluralLabel(): string
    {
        return match ($this) {
            self::Expense => 'Expenses',
            self::Income => 'Income',
            self::CashFlow => 'Cash flows',
        };
    }

    /**
     * Route-name prefix for this ledger's resource routes, so mixed
     * lists (the dashboard) can link a row to the right show page.
     */
    public function routePrefix(): string
    {
        return match ($this) {
            self::Expense => 'expenses',
            self::Income => 'income',
            self::CashFlow => 'cashflows',
        };
    }
}
