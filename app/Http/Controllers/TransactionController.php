<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Http\Requests\TransactionRequest;
use App\Models\BusinessUnit;
use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * One controller serves all three ledgers; ExpenseController,
 * IncomeController and CashFlowController are thin subclasses that pick
 * the model class and route prefix. The typed model's global scope keeps
 * each ledger blind to the others' rows.
 */
abstract class TransactionController extends Controller
{
    /**
     * @return class-string<Transaction>
     */
    abstract protected function modelClass(): string;

    abstract protected function type(): TransactionType;

    /**
     * Route name prefix: 'expenses', 'income', 'cashflows'.
     */
    abstract protected function routePrefix(): string;

    public function index(Request $request)
    {
        $transactions = $this->filteredQuery($request)
            ->with(['category', 'businessUnit', 'paymentMethod'])
            ->withCount('receipts')
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        // Total for the filtered set, in pence; formatted in the view.
        $totalGbpMinor = $this->filteredQuery($request)->sum('amount_gbp_minor');

        return view(
            'transactions.index',
            [
                'transactions' => $transactions,
                'totalGbpMinor' => $totalGbpMinor,
                'categories' => Category::active()->forType($this->type())->get(),
                'businessUnits' => BusinessUnit::active()->get(),
                'filters' => $request->only(['from', 'to', 'category_id', 'business_unit_id', 'q']),
                ...$this->viewContext(),
            ]
        );
    }

    public function create(Request $request)
    {
        $modelClass = $this->modelClass();

        if ($this->type() === TransactionType::Expense && $request->filled('capture_token')) {
            $this->flashCapturePrefill($request->string('capture_token')->toString());
        }

        return view(
            'transactions.create',
            [
                'transaction' => new $modelClass(['occurred_on' => now()->toDateString(), 'currency' => config('ledger.base_currency')]),
                ...$this->formLookups(),
                ...$this->viewContext(),
            ]
        );
    }

    public function store(TransactionRequest $request)
    {
        $transaction = $this->modelClass()::create($request->transactionData());

        if ($this->type() === TransactionType::Expense && $request->filled('capture_token')) {
            $this->attachCapturedReceipt($transaction, $request->string('capture_token')->toString());
        }

        return redirect()
            ->route("{$this->routePrefix()}.index")
            ->with('status', "Transaction saved: {$transaction->displayName()} (#{$transaction->id}).");
    }

    /**
     * Pulls the OCR result (if ready) for a just-captured photo into the
     * session's "old input", so the create form's existing old(...)
     * fallbacks pick it up with no changes to the form itself. The token
     * is always flashed, even without a ready OCR result, so the photo
     * still gets attached in store() -> attachCapturedReceipt().
     */
    private function flashCapturePrefill(string $token): void
    {
        $fields = ['capture_token' => $token];

        $pending = Cache::get("capture:{$token}");

        if ($pending && ($pending['status'] ?? null) === 'ready') {
            $ocr = $pending['ocr'];

            $categoryId = null;
            if (! empty($ocr['suggested_category'])) {
                $categoryId = Category::active()
                    ->forType($this->type())
                    ->where('name', $ocr['suggested_category'])
                    ->value('id');
            }

            $fields += array_filter(
                [
                    'occurred_on' => $ocr['date'] ?? null,
                    'counterparty' => $ocr['supplier'] ?? null,
                    'amount' => $ocr['amount'] ?? null,
                    'vat' => $ocr['vat'] ?? null,
                    'currency' => in_array($ocr['currency'] ?? null, settings('currencies'), true) ? $ocr['currency'] : null,
                    'category_id' => $categoryId,
                ]
            );
        }

        session()->flashInput($fields);
    }

    /**
     * Moves the pending capture's file into the transaction's own receipt
     * path and creates the Receipt row, carrying over whatever OCR result
     * is cached (none, if OCR never finished in time). Silently skips if
     * the cache entry has already expired — the same graceful degradation
     * as the rest of the OCR feature.
     */
    private function attachCapturedReceipt(Transaction $transaction, string $token): void
    {
        $pending = Cache::get("capture:{$token}");

        if (! $pending) {
            return;
        }

        $newPath = "receipts/{$transaction->id}/".basename($pending['path']);
        Storage::move($pending['path'], $newPath);

        $ocrJson = $pending['ocr'] ?? null;

        $transaction->receipts()->create(
            [
                'path' => $newPath,
                'original_filename' => $pending['original_filename'],
                'content_type' => $pending['content_type'],
                'byte_size' => $pending['byte_size'],
                'ocr_json' => $ocrJson,
            ]
        );

        if ($ocrJson) {
            $transaction->update(['ocr_json' => $ocrJson]);
        }

        Cache::forget("capture:{$token}");
    }

    public function show(string $id)
    {
        $transaction = $this->find($id);
        $transaction->load(['category', 'businessUnit', 'paymentMethod', 'receipts']);

        return view(
            'transactions.show',
            [
                'transaction' => $transaction,
                ...$this->viewContext(),
            ]
        );
    }

    public function edit(string $id)
    {
        return view(
            'transactions.edit',
            [
                'transaction' => $this->find($id),
                ...$this->formLookups(),
                ...$this->viewContext(),
            ]
        );
    }

    public function update(TransactionRequest $request, string $id)
    {
        $transaction = $this->find($id);
        $transaction->update($request->transactionData());

        return redirect()
            ->route("{$this->routePrefix()}.index")
            ->with('status', "Transaction updated: {$transaction->displayName()} (#{$transaction->id}).");
    }

    public function destroy(string $id)
    {
        $transaction = $this->find($id);
        // Hard delete - removes the row permanently.
        $transaction->delete();

        return redirect()
            ->route("{$this->routePrefix()}.index")
            ->with('status', "Transaction deleted: {$transaction->displayName()} (#{$transaction->id}).");
    }

    /**
     * Stream the filtered set as CSV, one column per field the accountant
     * needs — report group + account_code make year-end a straight import.
     */
    public function export(Request $request): StreamedResponse
    {
        $filename = sprintf('ledger-%s-%s.csv', $this->routePrefix(), now()->format('Y-m-d'));

        return response()->streamDownload(
            function () use ($request) {
                $out = fopen('php://output', 'w');

                fputcsv(
                    $out,
                    [
                        'Date', $this->type()->counterpartyLabel(), 'Description',
                        'Category', 'Report group', 'Account code', 'Business unit',
                        'Payment method', 'Currency', 'Amount', 'VAT', 'Total',
                        'FX rate', 'Amount (GBP)', 'Billable', 'Billed ref', 'Notes',
                    ]
                );

                $this->filteredQuery($request)
                    ->with(['category', 'businessUnit', 'paymentMethod'])
                    ->orderBy('occurred_on')
                    ->orderBy('id')
                // lazy() iterates in chunks behind the scenes — constant
                // memory no matter how many rows the range matches.
                    ->lazy()
                    ->each(
                        function (Transaction $transaction) use ($out) {
                            fputcsv(
                                $out,
                                [
                                    $transaction->occurred_on->toDateString(),
                                    $this->csvSafe($transaction->counterparty),
                                    $this->csvSafe($transaction->description),
                                    $this->csvSafe($transaction->category?->name),
                                    $this->csvSafe($transaction->category?->report_group),
                                    $this->csvSafe($transaction->category?->account_code),
                                    $this->csvSafe($transaction->businessUnit?->name),
                                    $this->csvSafe($transaction->paymentMethod?->name),
                                    $transaction->currency,
                                    $transaction->amount_major,
                                    $transaction->vat_major,
                                    $transaction->total_major,
                                    rtrim(rtrim((string) $transaction->fx_rate, '0'), '.'),
                                    $transaction->amount_gbp_major,
                                    $transaction->is_billable ? 'Yes' : 'No',
                                    $this->csvSafe($transaction->billed_ref),
                                    $this->csvSafe($transaction->notes),
                                ]
                            );
                        }
                    );

                fclose($out);
            },
            $filename,
            ['Content-Type' => 'text/csv']
        );
    }

    /**
     * Excel executes cell text starting with = + - @ (or tab/CR) as a
     * formula when a CSV is opened — free-text fields (which OCR can
     * pre-fill from a receipt someone else printed) get a leading
     * apostrophe so they always land as literal text.
     */
    protected function csvSafe(?string $value): ?string
    {
        if ($value !== null && preg_match('/^[=+\-@\t\r]/', $value)) {
            return "'".$value;
        }

        return $value;
    }

    protected function find(string $id): Transaction
    {
        // The subclass model's global scope guarantees the row belongs to
        // this ledger — an income id 404s on the expenses routes.
        return $this->modelClass()::findOrFail($id);
    }

    /**
     * The one query the index, total and export all share.
     */
    protected function filteredQuery(Request $request)
    {
        return $this->modelClass()::query()
            ->where('is_draft', false)
            ->occurredBetween($request->input('from'), $request->input('to'))
            ->when($request->input('category_id'), fn ($q, $id) => $q->where('category_id', $id))
            ->when($request->input('business_unit_id'), fn ($q, $id) => $q->where('business_unit_id', $id))
            ->when(
                $request->input('q'),
                function ($query, $term) {
                    // One search box across the free-text columns. The inner
                    // closure groups the ORs so they don't leak past the
                    // is_draft/date/type constraints. SQLite's LIKE has no
                    // escape character unless an ESCAPE clause declares one,
                    // so the wildcard escaping only works with the explicit
                    // clause — bound as a parameter to stay portable.
                    $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term).'%';

                    $query->where(
                        function ($q) use ($like) {
                            foreach (['counterparty', 'description', 'notes', 'billed_ref'] as $column) {
                                $q->orWhereRaw("{$column} LIKE ? ESCAPE ?", [$like, '\\']);
                            }
                        }
                    );
                }
            );
    }

    protected function formLookups(): array
    {
        return [
            'categories' => Category::active()->forType($this->type())->get(),
            'businessUnits' => BusinessUnit::active()->get(),
            'paymentMethods' => PaymentMethod::active()->get(),
        ];
    }

    /**
     * Type-specific labels and route names every view needs.
     */
    protected function viewContext(): array
    {
        return [
            'type' => $this->type(),
            'routePrefix' => $this->routePrefix(),
        ];
    }
}
