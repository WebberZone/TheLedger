<?php

namespace App\Jobs;

use App\Services\ReceiptOcr;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * OCRs a just-captured photo before any expense exists for it. Reads the
 * pending file location from the cache entry CaptureController::store()
 * created, then writes the parsed result back into the same entry so the
 * confirm page's poll (and later, prefilling /expenses/create) can pick it
 * up. See app/Services/ReceiptOcr.php for the actual OCR/parsing logic.
 */
class ExtractCapturePreview implements ShouldQueue
{
    use Queueable;

    public int $tries;

    public function __construct(
        public string $token,
    ) {
        $this->tries = settings('ocr.tries');
    }

    public function backoff(): array
    {
        return settings('ocr.backoff');
    }

    public function handle(ReceiptOcr $ocr): void
    {
        $key = "capture:{$this->token}";
        $pending = Cache::get($key);

        if ($pending === null) {
            // Confirm page was never opened, or the entry already expired.
            return;
        }

        $contents = Storage::get($pending['path']);

        if ($contents === null) {
            Cache::put($key, [...$pending, 'status' => 'failed'], now()->addSeconds(config('ledger.ocr.capture_ttl')));

            return;
        }

        try {
            $extracted = $ocr->extract($contents);
            $status = empty($extracted['raw_text']) ? 'failed' : 'ready';

            Cache::put(
                $key,
                [...$pending, 'status' => $status, 'ocr' => $extracted],
                now()->addSeconds(config('ledger.ocr.capture_ttl'))
            );
        } catch (\Exception) {
            Cache::put($key, [...$pending, 'status' => 'failed'], now()->addSeconds(config('ledger.ocr.capture_ttl')));
        }
    }
}
