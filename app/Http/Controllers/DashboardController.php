<?php

namespace App\Http\Controllers;

use App\Models\CashFlow;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;

/**
 * The landing page: month/year totals at a glance, recent activity, and
 * where this year's money went.
 *
 * Unlike ReportController (which loads everything and aggregates with
 * Collections), the dashboard asks the database for each number with
 * `sum()` — a handful of tiny aggregate queries instead of hydrating
 * every row.
 */
class DashboardController extends Controller
{
    public function index()
    {
        $monthStart = now()->startOfMonth()->toDateString();
        $yearStart = now()->startOfYear()->toDateString();

        $monthExpense = (int) $this->posted(Expense::query(), $monthStart)->sum('amount_gbp_minor');
        $monthIncome = (int) $this->posted(Income::query(), $monthStart)->sum('amount_gbp_minor');
        $yearExpense = (int) $this->posted(Expense::query(), $yearStart)->sum('amount_gbp_minor');
        $yearIncome = (int) $this->posted(Income::query(), $yearStart)->sum('amount_gbp_minor');

        // All-time totals: the same as the Reports "Total" line.
        $allTimeIncome = (int) $this->posted(Income::query(), null)->sum('amount_gbp_minor');
        $allTimeExpense = (int) $this->posted(Expense::query(), null)->sum('amount_gbp_minor');
        $allTimeProfit = $allTimeIncome - $allTimeExpense;
        $allTimeCashFlow = (int) $this->posted(CashFlow::query(), null)->sum('amount_gbp_minor');

        // Personal-card spend this year — what the company owes me back.
        $toReimburse = (int) $this->posted(Expense::query(), $yearStart)
            ->whereHas('paymentMethod', fn (Builder $q) => $q->where('is_personal', true))
            ->sum('amount_gbp_minor');

        // One query across all three ledgers; the base model has no type
        // scope, so the badge in the view shows which ledger each row is.
        $recent = Transaction::query()
            ->where('is_draft', false)
            ->with('category')
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        // GROUP BY in SQL, then hang the Category models off the rows.
        $topCategories = $this->posted(Expense::query(), $yearStart)
            ->selectRaw('category_id, SUM(amount_gbp_minor) AS total')
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->limit(5)
            ->with('category')
            ->get();

        $topIncomeCategories = $this->posted(Income::query(), $yearStart)
            ->selectRaw('category_id, SUM(amount_gbp_minor) AS total')
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->limit(5)
            ->with('category')
            ->get();

        return view(
            'dashboard',
            [
                'monthExpense' => $monthExpense,
                'monthIncome' => $monthIncome,
                'yearExpense' => $yearExpense,
                'yearIncome' => $yearIncome,
                'yearProfit' => $yearIncome - $yearExpense,
                'allTimeIncome' => $allTimeIncome,
                'allTimeExpense' => $allTimeExpense,
                'allTimeProfit' => $allTimeProfit,
                'allTimeCashFlow' => $allTimeCashFlow,
                'toReimburse' => $toReimburse,
                'recent' => $recent,
                'topCategories' => $topCategories,
                'topIncomeCategories' => $topIncomeCategories,
            ]
        );
    }

    /**
     * Confirmed (non-draft) rows from a given date onwards. The
     * Expense/Income subclasses carry a global scope on `type`, so
     * these queries can't leak rows from the other ledgers.
     *
     * @template TModel of Transaction
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function posted(Builder $query, ?string $from): Builder
    {
        return $query
            ->where('is_draft', false)
            ->occurredBetween($from, null);
    }
}
