<?php

namespace App\Jobs;

use App\Models\Receipt;
use App\Services\ReceiptOcr;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * OCRs a receipt already attached to an existing transaction (via
 * ReceiptController) and stores the extracted
 * {date, amount, vat, currency, supplier, suggested_category} on both the
 * receipt and its transaction. The raw model output is kept in ocr_json
 * for audit; nothing is auto-confirmed.
 */
class ExtractReceiptData implements ShouldQueue
{
    use Queueable;

    public int $tries;

    public function __construct(
        public Receipt $receipt,
    ) {
        $this->tries = settings('ocr.tries');
    }

    /**
     * Seconds to wait between retries (API blips, rate limits).
     */
    public function backoff(): array
    {
        return settings('ocr.backoff');
    }

    public function handle(ReceiptOcr $ocr): void
    {
        $contents = Storage::get($this->receipt->path);

        if ($contents === null) {
            $this->fail("Receipt file missing: {$this->receipt->path}");

            return;
        }

        // Deliberately no try/catch: an exception here propagates to the
        // queue worker, which retries the job with the configured
        // tries/backoff and only records it in failed_jobs after the last
        // attempt (the /tools page lists and retries those). fail() skips
        // the retries entirely, so it is reserved for the permanent
        // conditions above and below, where retrying can't help.
        $extracted = $ocr->extract($contents);

        if (empty($extracted['raw_text'])) {
            $this->fail('No text detected in receipt');

            return;
        }

        $this->receipt->update(['ocr_json' => $extracted]);
        $this->receipt->transaction->update(['ocr_json' => $extracted]);
    }
}
