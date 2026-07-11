<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes abandoned capture uploads: a photo lands in receipts/pending/
 * as soon as it's taken, but only moves into a real receipt path once the
 * user finishes the /expenses/create form. The cache entry pointing at it
 * expires on its own (see config('docket.ocr.capture_ttl')); this sweeps
 * the file left behind on disk for captures nobody ever completed.
 */
class PruneCapturedReceipts extends Command
{
    protected $signature = 'docket:prune-captures {--hours=24 : Delete pending files older than this many hours}';

    protected $description = 'Delete abandoned receipts/pending/ uploads from incomplete captures';

    public function handle(): int
    {
        $cutoff = now()->subHours((int) $this->option('hours'))->getTimestamp();
        $deleted = 0;

        foreach (Storage::files('receipts/pending') as $path) {
            if (Storage::lastModified($path) < $cutoff) {
                Storage::delete($path);
                $deleted++;
            }
        }

        $this->info("Deleted {$deleted} abandoned capture file(s).");

        return self::SUCCESS;
    }
}
