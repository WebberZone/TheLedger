<?php

namespace App\Http\Controllers;

use App\Jobs\ExtractCapturePreview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The two-tap mobile flow: tap one takes the photo and starts OCR in the
 * background; tap two happens on the real /expenses/create form, which
 * this flow hands off to once OCR is ready (see CaptureController::status()
 * and TransactionController::create()). Nothing is persisted here — the
 * upload and its OCR result live in the cache under a token until the
 * expense is actually saved.
 */
class CaptureController extends Controller
{
    public function create()
    {
        return view('capture.create');
    }

    /**
     * Photo just landed: stash it on disk under a token, queue OCR against
     * that token, and go straight to the "reading receipt" screen.
     */
    public function store(Request $request)
    {
        $request->validate(
            [
                'photo' => [
                    'required',
                    'file',
                    'mimes:'.settings('receipts.mimes'),
                    'max:'.settings('receipts.max_kb'),
                ],
            ]
        );

        $file = $request->file('photo');
        $token = (string) Str::ulid();
        $path = $file->storeAs('receipts/pending', $token.'.'.$file->extension());

        Cache::put(
            "capture:{$token}",
            [
                'status' => 'processing',
                'path' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'content_type' => $file->getMimeType(),
                'byte_size' => $file->getSize(),
            ],
            now()->addSeconds(config('docket.ocr.capture_ttl'))
        );

        // The job picks Vision or falls back to the local Tesseract binary,
        // so no credentials are required to queue it.
        ExtractCapturePreview::dispatch($token);

        return redirect()->route('capture.confirm', $token);
    }

    /**
     * The "reading receipt" interim screen: shows the photo and polls
     * status() until OCR is ready, then hands off to /expenses/create.
     */
    public function confirm(string $token)
    {
        if (Cache::get("capture:{$token}") === null) {
            return redirect()->route('capture.create')
                ->with('status', 'That capture has expired — take the photo again.');
        }

        return view('capture.confirm', ['token' => $token]);
    }

    /**
     * Polled by the confirm page until OCR has landed (or given up).
     */
    public function status(string $token)
    {
        $pending = Cache::get("capture:{$token}");

        return response()->json(['status' => $pending['status'] ?? 'expired']);
    }

    /**
     * Streams the not-yet-attached photo for the confirm screen's preview.
     */
    public function photo(string $token): StreamedResponse
    {
        $pending = Cache::get("capture:{$token}");

        abort_unless($pending && Storage::exists($pending['path']), 404);

        return Storage::response($pending['path']);
    }
}
