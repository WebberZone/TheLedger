<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Models\AccountBalance;
use App\Models\CashFlow;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Export the app's data into a copy of the original Accounts Tracker
 * spreadsheet — same sheets, tables, formulas, and styling, populated
 * with live data via a Python/openpyxl script (PhpSpreadsheet's Excel
 * Table support is too unreliable for structured-reference formulas;
 * see the export walkthrough in the project history for why).
 */
class TrackerExportController extends Controller
{
    /**
     * Fixed snapshot columns baked into the template's Balances sheet.
     * If new snapshot dates are recorded that don't match one of these,
     * they're silently skipped — the template would need a new column
     * to show them (documented limitation, not a bug).
     */
    private const BALANCE_DATE_COLUMNS = [
        [
            'col_letter' => 'D',
            'date' => '2023-12-31',
        ],
        [
            'col_letter' => 'E',
            'date' => '2024-12-31',
        ],
        [
            'col_letter' => 'F',
            'date' => '2025-12-31',
        ],
        [
            'col_letter' => 'G',
            'date' => '2026-03-17',
        ],
    ];

    /**
     * Preferred row order on the Balances sheet, matching the original.
     */
    private const ACCOUNT_ORDER = ['Revolut Business', 'PayPal', 'Wise'];

    public function tracker()
    {
        $paypalUsd = $this->paypalTransactions('USD');
        $paypalEur = $this->paypalTransactions('EUR');

        $payload = [
            'income' => $this->ledgerRows(Income::query()->where('is_draft', false)->with(['category', 'paymentMethod'])->orderBy('occurred_on')->get()),
            'expense' => $this->ledgerRows(Expense::query()->where('is_draft', false)->with(['category', 'paymentMethod'])->orderBy('occurred_on')->get()),
            'cash_flow' => $this->ledgerRows(CashFlow::query()->where('is_draft', false)->with(['category', 'paymentMethod'])->orderBy('occurred_on')->get()),
            'years' => $this->allYears(),
            'paypal_usd' => $this->paypalLedgerRows($paypalUsd),
            'paypal_eur' => $this->paypalLedgerRows($paypalEur),
            'balances' => $this->balanceRows($paypalUsd, $paypalEur),
            'balance_date_columns' => self::BALANCE_DATE_COLUMNS,
        ];

        $tmpDir = storage_path('app/tmp');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $jsonPath = $tmpDir.'/tracker-export-'.uniqid().'.json';
        $outputPath = $tmpDir.'/Accounts Tracker-'.uniqid().'.xlsx';
        file_put_contents($jsonPath, json_encode($payload));

        $process = new Process(
            [
                config('ledger.export.python_bin'),
                config('ledger.export.script'),
                $this->templatePath(),
                $jsonPath,
                $outputPath,
            ]
        );
        $process->run();

        unlink($jsonPath);

        if (! $process->isSuccessful()) {
            if (is_file($outputPath)) {
                unlink($outputPath);
            }
            throw new ProcessFailedException($process);
        }

        return (new BinaryFileResponse($outputPath))
            ->deleteFileAfterSend(true)
            ->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'Accounts Tracker.xlsx');
    }

    /**
     * The owner's own template (built from the original spreadsheet and
     * kept out of the repository) when it is installed, otherwise the
     * blank one bundled with the app (python/scripts/build_blank_template.py),
     * which has the same sheets, tables and Summary formulas.
     */
    private function templatePath(): string
    {
        $own = storage_path('app/templates/tracker-template.xlsx');

        return is_file($own) ? $own : resource_path('templates/tracker-template.xlsx');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function ledgerRows($transactions): array
    {
        return $transactions->map(
            fn (Transaction $t) => [
                'date' => $t->occurred_on->toDateString(),
                'category' => $t->category?->name,
                'counterparty' => $t->counterparty,
                'currency' => $t->currency,
                'fx_rate' => $t->currency === config('ledger.base_currency') ? null : (float) $t->fx_rate,
                'amount' => $t->amount_minor / 100,
                'vat' => $t->vat_minor / 100,
                'amount_gbp' => $t->amount_gbp_minor / 100,
                'description' => $t->description,
                'payment_method' => $t->paymentMethod?->name,
            ]
        )->all();
    }

    private function allYears(): array
    {
        // Base Transaction has no type scope, so query() spans all three
        // ledgers while still excluding soft-deleted rows (which
        // withoutGlobalScopes() would have resurrected into the export).
        return Transaction::query()
            ->where('is_draft', false)
            ->get()
            ->pluck('occurred_on')
            ->map(fn ($date) => $date->year)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * The PayPal sheet is a running ledger across all transaction types
     * on that payment method — income/cash-flow add, expenses subtract —
     * same signed-amount logic as LedgerController.
     */
    private function paypalTransactions(string $currency)
    {
        return Transaction::query()
            ->where('is_draft', false)
            ->where('currency', $currency)
            ->whereHas('paymentMethod', fn ($q) => $q->where('name', 'PayPal'))
            ->orderBy('occurred_on')
            ->orderBy('id')
            ->get();
    }

    /**
     * Shape PayPal transactions into signed rows for the PayPal sheet.
     *
     * @param  Collection<int, Transaction>  $transactions  PayPal transactions in one currency, oldest first.
     * @return array<int, array<string, mixed>>
     */
    private function paypalLedgerRows($transactions): array
    {
        return $transactions->map(
            fn (Transaction $t) => [
                'date' => $t->occurred_on->toDateString(),
                'description' => $t->description,
                'source' => $t->counterparty,
                'amount' => ($t->type === TransactionType::Expense ? -1 : 1) * ($t->total_minor / 100),
            ]
        )->all();
    }

    /**
     * The original tracker linked PayPal's Balances row to the PayPal
     * sheet via cell formulas (e.g. `=PayPal!F9`) — the exact row for a
     * given cutoff date. Our PayPal ledger length varies with real
     * transaction data, so pinning to a row number isn't robust; instead
     * we compute the true running balance as of each cutoff date
     * directly from the transactions, which is at least as accurate.
     */
    private function balanceAsOf($transactions, string $cutoffDate): ?float
    {
        $running = $transactions->filter(fn (Transaction $t) => $t->occurred_on->toDateString() <= $cutoffDate)
            ->sum(fn (Transaction $t) => ($t->type === TransactionType::Expense ? -1 : 1) * $t->total_minor);

        return $transactions->isEmpty() ? null : $running / 100;
    }

    private function balanceRows($paypalUsd, $paypalEur): array
    {
        $balances = AccountBalance::orderBy('account')->orderBy('currency')->get();

        $rows = $balances
            ->groupBy(fn ($b) => $b->account.'|'.$b->currency)
            ->map(
                function ($group) {
                    $first = $group->first();

                    return [
                        'account' => $first->account,
                        'currency' => $first->currency,
                        'balances' => $group->mapWithKeys(
                            fn ($b) => [
                                $b->balance_on->toDateString() => $b->balance_minor / 100,
                            ]
                        )->all(),
                        'notes' => $group->pluck('notes')->filter()->unique()->implode('; ') ?: null,
                    ];
                }
            )
            ->values();

        // PayPal has no manually-recorded snapshots in AccountBalance —
        // its balance was always derived from the ledger. Add it here so
        // the export doesn't just drop the account entirely.
        foreach ([['USD', $paypalUsd], ['EUR', $paypalEur]] as [$currency, $transactions]) {
            if ($transactions->isEmpty()) {
                continue;
            }

            $rows->push(
                [
                    'account' => 'PayPal',
                    'currency' => $currency,
                    'balances' => collect(self::BALANCE_DATE_COLUMNS)
                        ->mapWithKeys(fn ($dc) => [$dc['date'] => $this->balanceAsOf($transactions, $dc['date'])])
                        ->filter(fn ($v) => $v !== null)
                        ->all(),
                    'notes' => null,
                ]
            );
        }

        // Sort to match the original template's row order: Revolut
        // Business, PayPal (USD before EUR), Wise, then anything new.
        $currencyOrder = settings('currencies');

        return $rows->sortBy(
            function ($row) use ($currencyOrder) {
                $priority = array_search($row['account'], self::ACCOUNT_ORDER);
                $currencyPriority = array_search($row['currency'], $currencyOrder);

                return [
                    $priority === false ? count(self::ACCOUNT_ORDER) : $priority,
                    $row['account'],
                    $currencyPriority === false ? count($currencyOrder) : $currencyPriority,
                ];
            }
        )->values()->all();
    }
}
