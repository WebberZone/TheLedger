<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Models\Transaction;
use Illuminate\Support\Collection;

/**
 * The Summary sheet from the old tracker: per-year Income/Expense/
 * Profit/Cash-flow, expense breakdowns by category and payment method.
 *
 * Aggregation is done with Collections in PHP rather than SQL — a few
 * hundred rows a year for one user; readability wins over cleverness.
 */
class ReportController extends Controller
{
    public function index()
    {
        $transactions = Transaction::query()
            ->where('is_draft', false)
            ->with(['category', 'paymentMethod'])
            ->get();

        if ($transactions->isEmpty()) {
            return view(
                'reports.index',
                [
                    'years' => collect(),
                    'summary' => collect(),
                    'expensesByGroup' => collect(),
                    'expensesByCategory' => collect(),
                    'incomeByCategory' => collect(),
                    'byPaymentMethod' => collect(),
                    'personalTotals' => collect(),
                ]
            );
        }

        $firstYear = (int) $transactions->min('occurred_on')->format('Y');
        $lastYear = (int) $transactions->max('occurred_on')->format('Y');

        // Mirror the tracker: the first reported year is cumulative, so
        // pre-history (2020-23 expenses) lands in the earliest bucket.
        $reportFrom = max($firstYear, $lastYear - 10);
        $years = collect(range($reportFrom, $lastYear));

        $bucket = fn (Transaction $t) => max((int) $t->occurred_on->format('Y'), $reportFrom);

        $byType = fn (TransactionType $type) => $transactions
            ->filter(fn ($t) => $t->type === $type);

        $yearTotals = function (Collection $set) use ($years, $bucket) {
            $grouped = $set->groupBy($bucket)->map->sum('amount_gbp_minor');

            return $years->mapWithKeys(fn ($y) => [$y => $grouped->get($y, 0)]);
        };

        $income = $yearTotals($byType(TransactionType::Income));
        $expense = $yearTotals($byType(TransactionType::Expense));
        $cashFlow = $yearTotals($byType(TransactionType::CashFlow));

        $summary = $years->map(
            fn ($y) => [
                'year' => $y,
                'income' => $income[$y],
                'expense' => $expense[$y],
                'profit' => $income[$y] - $expense[$y],
                'cash_flow' => $cashFlow[$y],
            ]
        );

        // rows = label, cells = year totals. Used for all four matrices.
        $matrix = function (Collection $set, callable $label) use ($years, $bucket) {
            return $set
                ->groupBy(fn ($t) => $label($t) ?? '(uncategorised)')
                ->sortKeys()
                ->map(
                    function (Collection $rows) use ($years, $bucket) {
                        $grouped = $rows->groupBy($bucket)->map->sum('amount_gbp_minor');

                        return $years->mapWithKeys(fn ($y) => [$y => $grouped->get($y, 0)]);
                    }
                );
        };

        $expenses = $byType(TransactionType::Expense);

        return view(
            'reports.index',
            [
                'years' => $years,
                'summary' => $summary,
                // Accountant-facing rollup (the tracker's "mapped category").
                'expensesByGroup' => $matrix($expenses, function ($t) {
                    return $t->category !== null ? ($t->category->report_group ?? $t->category->name) : null;
                }),
                'expensesByCategory' => $matrix($expenses, fn ($t) => $t->category?->name),
                'incomeByCategory' => $matrix($byType(TransactionType::Income), fn ($t) => $t->category?->name),
                'byPaymentMethod' => $matrix($expenses, fn ($t) => $t->paymentMethod?->name),
                // The tracker's "Personal cards" subtotal — what needs
                // reimbursing from the company.
                'personalTotals' => $yearTotals($expenses->filter(fn ($t) => $t->paymentMethod?->is_personal)),
            ]
        );
    }
}
