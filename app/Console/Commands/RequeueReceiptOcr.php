<?php

namespace App\Console\Commands;

use App\Jobs\ExtractReceiptData;
use App\Models\Receipt;
use Illuminate\Console\Command;

/**
 * Re-queues the OCR job for receipts. By default only receipts that never
 * got a result (ocr_json is null — e.g. the queue worker was down, or the
 * job exhausted its retries) are picked up; --all re-runs every receipt,
 * and a specific ID re-runs just that one regardless of state.
 */
class RequeueReceiptOcr extends Command
{
    protected $signature = 'docket:ocr
                            {receipt? : Re-run OCR for a single receipt ID}
                            {--all : Re-run OCR for every receipt, even ones that already have results}';

    protected $description = 'Queue OCR extraction for receipts (default: only those with no OCR result yet)';

    public function handle(): int
    {
        if (($id = $this->argument('receipt')) !== null) {
            $receipt = Receipt::find($id);

            if ($receipt === null) {
                $this->error("Receipt {$id} not found.");

                return self::FAILURE;
            }

            ExtractReceiptData::dispatch($receipt);
            $this->info("Queued OCR for receipt {$receipt->id} ({$receipt->original_filename}).");

            return self::SUCCESS;
        }

        $receipts = Receipt::query()
            ->when(! $this->option('all'), fn ($query) => $query->whereNull('ocr_json'))
            ->get();

        foreach ($receipts as $receipt) {
            ExtractReceiptData::dispatch($receipt);
        }

        $this->info(
            $this->option('all')
                ? "Queued OCR for all {$receipts->count()} receipt(s)."
                : "Queued OCR for {$receipts->count()} receipt(s) with no OCR result."
        );

        if ($receipts->isNotEmpty()) {
            $this->comment('Jobs run when the queue worker picks them up (composer dev locally, queue:work in production).');
        }

        return self::SUCCESS;
    }
}
