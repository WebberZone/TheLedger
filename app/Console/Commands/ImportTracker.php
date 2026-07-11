<?php

namespace App\Console\Commands;

use App\Enums\TransactionType;
use App\Models\AccountBalance;
use App\Models\CashFlow;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Income;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use Illuminate\Console\Command;

/**
 * One-off import of the Accounts Tracker spreadsheet, from CSVs produced
 * by the export script (income.csv, expenses.csv, cashflows.csv,
 * balances.csv in the given directory).
 *
 *   php artisan docket:import-tracker /path/to/tracker-csv
 */
class ImportTracker extends Command
{
    protected $signature = 'docket:import-tracker {dir : Directory containing the exported CSVs}
                            {--force : Import even if transactions already exist}';

    protected $description = 'Import income/expenses/cash flows/balances exported from the Accounts Tracker spreadsheet';

    public function handle(): int
    {
        $dir = rtrim($this->argument('dir'), '/');

        if (! is_dir($dir)) {
            $this->error("Not a directory: {$dir}");

            return self::FAILURE;
        }

        if (Transaction::withoutGlobalScopes()->exists() && ! $this->option('force')) {
            $this->error('Transactions already exist. Re-run with --force to import anyway.');

            return self::FAILURE;
        }

        $ledgers = [
            'income.csv' => [Income::class, TransactionType::Income],
            'expenses.csv' => [Expense::class, TransactionType::Expense],
            'cashflows.csv' => [CashFlow::class, TransactionType::CashFlow],
        ];

        foreach ($ledgers as $file => [$modelClass, $type]) {
            $count = 0;

            foreach ($this->readCsv("{$dir}/{$file}") as $row) {
                $modelClass::create(
                    [
                        'occurred_on' => $row['date'],
                        'counterparty' => $row['counterparty'],
                        'description' => $row['description'] ?: null,
                        'amount_minor' => (int) round((float) $row['amount'] * 100),
                        'vat_minor' => (int) round((float) $row['vat'] * 100),
                        'currency' => $row['currency'],
                        'fx_rate' => (float) $row['fx_rate'],
                        'amount_gbp_minor' => (int) round((float) $row['amount_gbp'] * 100),
                        'category_id' => $this->categoryId($row['category'], $type),
                        'payment_method_id' => $this->paymentMethodId($row['payment_method']),
                    ]
                );
                $count++;
            }

            $this->info(sprintf('%-14s %d rows', $file, $count));
        }

        $count = 0;
        foreach ($this->readCsv("{$dir}/balances.csv") as $row) {
            AccountBalance::updateOrCreate(
                [
                    'account' => $row['account'],
                    'currency' => $row['currency'],
                    'balance_on' => $row['balance_on'],
                ],
                [
                    'balance_minor' => (int) round((float) $row['balance'] * 100),
                    'notes' => $row['notes'] ?: null,
                ]
            );
            $count++;
        }
        $this->info(sprintf('%-14s %d rows', 'balances.csv', $count));

        $this->newLine();
        $this->table(
            ['Ledger', 'Rows', 'Total (GBP)'],
            collect(
                [
                    ['Income', Income::query()],
                    ['Expenses', Expense::query()],
                    ['Cash flows', CashFlow::query()],
                ]
            )->map(
                fn ($pair) => [
                    $pair[0],
                    $pair[1]->clone()->count(),
                    '£'.number_format($pair[1]->clone()->sum('amount_gbp_minor') / 100, 2),
                ]
            )
        );

        return self::SUCCESS;
    }

    /**
     * @return \Generator<array<string, string>>
     */
    private function readCsv(string $path): \Generator
    {
        if (! is_file($path)) {
            $this->warn("Missing file, skipped: {$path}");

            return;
        }

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);

        while (($row = fgetcsv($handle)) !== false) {
            yield array_combine($header, $row);
        }

        fclose($handle);
    }

    private function categoryId(string $name, TransactionType $type): ?int
    {
        if ($name === '') {
            return null;
        }

        // Seeded categories carry the report_group mapping; anything new
        // gets created with itself as the group.
        return Category::firstOrCreate(
            ['name' => $name, 'type' => $type->value],
            ['report_group' => $name]
        )->id;
    }

    private function paymentMethodId(string $name): ?int
    {
        if ($name === '') {
            return null;
        }

        // Case-insensitive: the tracker has both 'PayPal' and 'Paypal'.
        $existing = PaymentMethod::whereRaw('lower(name) = ?', [mb_strtolower($name)])->first();

        return ($existing ?? PaymentMethod::create(['name' => $name]))->id;
    }
}
