<?php

namespace App\Http\Controllers;

use App\Jobs\ExtractReceiptData;
use App\Models\Expense;
use App\Models\Receipt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReceiptController extends Controller
{
    /**
     * Attach one or more images/PDFs to an expense.
     */
    public function store(Request $request, Expense $expense)
    {
        $request->validate(
            [
                'receipts' => ['required', 'array'],
                'receipts.*' => ['file', 'mimes:'.settings('receipts.mimes'), 'max:'.settings('receipts.max_kb')],
            ]
        );

        foreach ($request->file('receipts') as $file) {
            // store() writes to the private local disk with a random
            // hashed filename and returns the path.
            $path = $file->store("receipts/{$expense->id}");

            $receipt = $expense->receipts()->create(
                [
                    'path' => $path,
                    'original_filename' => $file->getClientOriginalName(),
                    'content_type' => $file->getMimeType(),
                    'byte_size' => $file->getSize(),
                ]
            );

            // Queue OCR extraction; the job picks Vision or falls back to
            // the local Tesseract binary, so no credentials are required.
            ExtractReceiptData::dispatch($receipt);
        }

        return back()->with('status', 'Receipt uploaded successfully.');
    }

    /**
     * Stream a receipt back from private storage. The file has no public
     * URL — this authed route is the only way to reach it.
     */
    public function show(Receipt $receipt): StreamedResponse
    {
        abort_unless(Storage::exists($receipt->path), 404);

        return Storage::response($receipt->path, $receipt->original_filename);
    }
}
