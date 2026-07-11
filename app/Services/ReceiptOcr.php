<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Category;
use Google\Cloud\Vision\V1\AnnotateImageRequest;
use Google\Cloud\Vision\V1\BatchAnnotateImagesRequest;
use Google\Cloud\Vision\V1\Client\ImageAnnotatorClient;
use Google\Cloud\Vision\V1\Feature;
use Google\Cloud\Vision\V1\Feature\Type;
use Google\Cloud\Vision\V1\Image;
use Illuminate\Support\Facades\Log;
use thiagoalessio\TesseractOCR\TesseractOCR;

/**
 * Runs OCR on a receipt image (or PDF) and parses the result into
 * {date, amount, vat, currency, supplier, suggested_category, raw_text}.
 * Google Cloud Vision is used when credentials are configured; Tesseract
 * (free, local) is the fallback when they're absent or the Vision call
 * errors. Shared by the queued job that OCRs receipts attached to an
 * existing transaction and the capture flow that OCRs a photo before the
 * expense exists.
 */
class ReceiptOcr
{
    public function extract(string $contents): array
    {
        return $this->parseReceiptText($this->detectText($contents));
    }

    /**
     * Detect text via Google Vision when credentials are configured, falling
     * back to Tesseract (no credentials needed) when they're absent or the
     * Vision call itself errors — e.g. an expired key or a rate limit.
     */
    protected function detectText(string $contents): string
    {
        if ($this->visionCredentials() !== null) {
            try {
                return $this->detectTextViaVision($contents);
            } catch (\Exception $e) {
                Log::warning("Google Vision OCR failed, falling back to Tesseract: {$e->getMessage()}");
            }
        }

        return $this->detectTextViaTesseract($contents);
    }

    /**
     * Run Google Vision text detection and return the full detected text.
     * The Vision client is final, so this method is the seam tests stub.
     */
    protected function detectTextViaVision(string $contents): string
    {
        // Resolved through the container so it can be swapped in tests.
        $client = app(
            ImageAnnotatorClient::class,
            ['options' => ['credentials' => $this->visionCredentials()]]
        );

        try {
            $image = (new Image())->setContent($contents);
            $feature = (new Feature())->setType(Type::TEXT_DETECTION);
            $request = (new BatchAnnotateImagesRequest())->setRequests(
                [
                    (new AnnotateImageRequest())
                        ->setImage($image)
                        ->setFeatures([$feature]),
                ]
            );

            // Vision batches, so the whole document's text sits in the first
            // annotation of the first (only) response. The protobuf PHPDocs
            // type the getters as non-nullable arrays, but an empty batch or
            // a no-text response yields no element at [0], so guard the
            // offsets explicitly rather than relying on nullsafe on the
            // declared types.
            $response = $client->batchAnnotateImages($request);
            $firstResponse = $response->getResponses()[0] ?? null;
            $annotations = $firstResponse?->getTextAnnotations() ?? [];
            $firstAnnotation = $annotations[0] ?? null;

            return $firstAnnotation?->getDescription() ?? '';
        } finally {
            $client->close();
        }
    }

    /**
     * Run Tesseract (shells out to the local binary) and return the
     * detected text. Kept as its own protected method, like the Vision
     * seam above, so tests can stub it independently.
     *
     * PSM 6 ("assume a single uniform block of text") rather than
     * Tesseract's default PSM 3 (full automatic page/layout segmentation):
     * a receipt is exactly a single narrow text block, and the default's
     * layout analysis was dropping whole lines (verified against a real
     * receipt — the actual "Amount due £62.41" line was silently missing
     * from the PSM 3 output entirely, not misread).
     */
    protected function detectTextViaTesseract(string $contents): string
    {
        return (new TesseractOCR())
            ->executable(config('docket.ocr.tesseract_binary'))
            ->imageData($contents, strlen($contents))
            ->psm(6)
            ->run();
    }

    /**
     * The service account key. Stored in the environment as raw JSON, so
     * decode it to the array the client expects; a bare path is passed
     * through untouched.
     *
     * @return array<string, mixed>|string|null
     */
    private function visionCredentials(): array|string|null
    {
        $credentials = config('services.google_vision.credentials');

        if (is_string($credentials) && str_starts_with(trim($credentials), '{')) {
            return json_decode($credentials, true);
        }

        return $credentials;
    }

    /**
     * Parse receipt text to extract structured data.
     */
    private function parseReceiptText(string $text): array
    {
        $categories = Category::active()
            ->forType(TransactionType::Expense)
            ->pluck('name')
            ->implode(', ');

        $lines = array_values(array_filter(array_map('trim', explode("\n", $text))));

        $result = [
            'date' => null,
            'amount' => null,
            'vat' => null,
            'currency' => config('docket.base_currency'),
            'supplier' => null,
            'suggested_category' => null,
            'raw_text' => $text,
        ];

        // Extract date — try numeric formats first, then "6 August 2025".
        foreach ($lines as $line) {
            if (preg_match('/(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/', $line, $matches)) {
                $date = $this->normalizeDate($matches[1]);
                if ($date) {
                    $result['date'] = $date;
                    break;
                }
            }
            if (preg_match(
                '/(\d{1,2}(?:st|nd|rd|th)?\s+(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec|January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{4})/i',
                $line,
                $matches
            )) {
                $date = $this->normalizeDate($matches[1]);
                if ($date) {
                    $result['date'] = $date;
                    break;
                }
            }
        }

        // Extract amount: prefer an explicit "total"/"amount due" line (not
        // "sub-total", which is pre-service-charge) over the largest number
        // found — receipts print plenty of numbers that aren't the amount
        // (reference numbers, table numbers, item counts).
        $amount = null;
        foreach ($lines as $line) {
            if (preg_match('/\b(?:total|amount\s*due|balance\s*due|amount\s*payable|grand\s*total|total\s*incl|to\s*pay|card\s*payment)\b/i', $line)
                && ! preg_match('/\bsub[\s\-]?total\b/i', $line)
                && preg_match('/(\d+[.,]\d{2})/', $line, $matches)) {
                $amount = $this->parseDecimal($matches[1]);
                break;
            }
        }

        // Fall back: collect ALL plausible numbers. Receipts often print
        // the true total more than once (subtotal breakdown, payment
        // method line, "amount paid" line), so prefer a value that
        // repeats over a single larger outlier — a lone bigger number is
        // more likely a misread reference/tax-ID than the real total.
        // With no repeat at all, fall back to the largest.
        if ($amount === null) {
            $candidates = [];
            foreach ($lines as $line) {
                if (preg_match('/\b(?:vat|tax)\b/i', $line) || str_contains($line, '#')) {
                    continue;
                }

                if (preg_match('/[£$€]?\s*(\d+[.,]\d{2})/', $line, $matches)) {
                    $candidate = $this->parseDecimal($matches[1]);
                    if ($candidate > 0 && $candidate < 10000) {
                        $candidates[] = $candidate;
                    }
                }
            }
            if ($candidates !== []) {
                $amount = $this->mostPlausibleAmount($candidates);
            }
        }

        $result['amount'] = $amount !== null ? number_format($amount, 2) : null;

        // Extract VAT/tax — check the current line first, then peek at the
        // next line in case the amount appears below the label.
        $lineCount = count($lines);
        for ($i = 0; $i < $lineCount; $i++) {
            $line = $lines[$i];
            if (! preg_match('/\b(?:vat|tax)\b/i', $line)) {
                continue;
            }

            // Same line.
            if (preg_match('/[£$€]?\s*(\d+[.,]\d{2})/', $line, $matches)) {
                $vat = $this->parseDecimal($matches[1]);
                if ($vat >= 0 && $vat < 10000) {
                    $result['vat'] = number_format($vat, 2);
                    break;
                }
            }

            // Next line (e.g. "VAT" alone on one line, amount on the next) —
            // but not if that next line is itself a total/subtotal line, or
            // "VAT" alone (no value anywhere near it) would steal the total.
            if ($i + 1 < $lineCount) {
                $nextLine = $lines[$i + 1];
                if (preg_match('/\b(?:total|amount\s*due|balance\s*due|sub[\s\-]?total)\b/i', $nextLine)) {
                    continue;
                }

                if (preg_match('/[£$€]?\s*(\d+[.,]\d{2})/', $nextLine, $matches)) {
                    $vat = $this->parseDecimal($matches[1]);
                    if ($vat >= 0 && $vat < 10000) {
                        $result['vat'] = number_format($vat, 2);
                        break;
                    }
                }
            }
        }

        // Extract supplier — scan the first few lines, skipping headers,
        // addresses, VAT/company numbers, phone/web noise.
        $result['supplier'] = $this->extractSupplier($lines, $result['date']);
        $result['suggested_category'] = $this->suggestCategory($result['supplier'], $categories);

        return $result;
    }

    /**
     * Walk the first lines of OCR output looking for the merchant name.
     * Skip known noise (receipt headers, addresses, phone/web, VAT
     * numbers) and take the first line left standing. Deliberately does
     * NOT try to join short names across lines — most UK retail names are
     * short (Tesco, Aldi, M&S, Boots), and a following line is at least as
     * likely to be an unrelated tagline ("Every Little Helps") as it is a
     * genuine continuation of the name, so guessing does more harm than
     * good here.
     */
    private function extractSupplier(array $lines, ?string $alreadyFoundDate): ?string
    {
        $top = array_slice($lines, 0, 6);

        foreach ($top as $line) {
            if ($this->isSupplierNoise($line, $alreadyFoundDate)) {
                continue;
            }

            if (strlen($line) > 2 && ! preg_match('/[£$€\d]/', $line)) {
                return $line;
            }
        }

        return null;
    }

    /**
     * Return true if a line looks like receipt boilerplate, not the
     * merchant name — headers, addresses, phone numbers, URLs, VAT
     * numbers, or already-found dates.
     */
    private function isSupplierNoise(string $line, ?string $alreadyFoundDate): bool
    {
        $normalised = mb_strtolower(trim($line));

        if ($normalised === '') {
            return true;
        }

        // Headers plastered on nearly every receipt.
        if (preg_match('/\b(receipt|invoice|order|tax\s*invoice|bill|draft|copy|duplicate|visitor?|customer|table|check|sales?|transaction|thanks?|thank\s*you)\b/', $normalised)) {
            return true;
        }

        // Date we already extracted — not a supplier name.
        if ($alreadyFoundDate !== null && str_contains($line, $alreadyFoundDate)) {
            return true;
        }

        // UK postcodes and ZIP codes.
        if (preg_match('/\b[A-Z]{1,2}\d[A-Z\d]?\s*\d[A-Z]{2}\b/i', $line)) {
            return true;
        }

        // Phone / fax numbers.
        if (preg_match('/(?:\+44|0)\s*\d{2,4}[\s\-]?\d{3,4}[\s\-]?\d{3,4}/', $line)) {
            return true;
        }

        // URLs and email addresses.
        if (preg_match('/https?:\/\/|www\.|@\S+\.\S+/', $normalised)) {
            return true;
        }

        // VAT / company / registration numbers — the "no/num/#" suffix is
        // required so this doesn't match a bare "co" inside a business name
        // like "Co-op" (a hyphen is a word boundary, so \bco\b alone would
        // otherwise match it).
        if (preg_match('/\b(?:vat|tax\s*id|company|co\.?|reg(?:istration)?)\s*(?:no\.?|num(?:ber)?|#)\b/', $normalised)) {
            return true;
        }

        // Street addresses — require a leading house/building number
        // followed by a known street suffix.
        if (preg_match('/\d+\s+\w+\s+(road|street|lane|avenue|drive|square|place|court|close|park|green|hill|row|walk|way|gardens|mews|crescent|yard|terrace|buildings)\b/ui', $line)) {
            return true;
        }

        return false;
    }

    /**
     * Some receipts (and some OCR misreads) use a comma as the decimal
     * separator (e.g. "54,50") rather than a dot.
     */
    private function parseDecimal(string $value): float
    {
        return (float) str_replace(',', '.', $value);
    }

    /**
     * @param  list<float>  $candidates
     */
    private function mostPlausibleAmount(array $candidates): float
    {
        $counts = array_count_values(array_map(fn (float $c) => number_format($c, 2), $candidates));
        arsort($counts);
        $mostFrequentValue = array_key_first($counts);

        if ($counts[$mostFrequentValue] > 1) {
            return (float) $mostFrequentValue;
        }

        return max($candidates);
    }

    /**
     * Normalize various date formats to YYYY-MM-DD.
     */
    private function normalizeDate(string $date): ?string
    {
        // Numeric: 06/08/2025, 6-8-25, 6.8.2025. The 2-digit-year ('y')
        // variant must be tried BEFORE the 4-digit ('Y') one: PHP's
        // createFromFormat happily accepts a bare 2-digit value for 'Y'
        // as a literal year (e.g. "12/06/24" -> year 24, not 2024) rather
        // than failing and letting the 2-digit format take over, so trying
        // 'Y' first would silently produce the wrong century.
        $numericFormats = [
            'd/m/y', 'd/m/Y', 'j/n/y', 'j/n/Y',
            'd-m-y', 'd-m-Y', 'j-n-y', 'j-n-Y',
            'd\.m\.y', 'd\.m\.Y', 'j\.n\.y', 'j\.n\.Y',
            'm/d/y', 'm/d/Y', 'n/j/y', 'n/j/Y',
        ];

        foreach ($numericFormats as $format) {
            $dateObj = \DateTime::createFromFormat($format, $date);
            if ($dateObj) {
                return $dateObj->format('Y-m-d');
            }
        }

        // Textual: "6 August 2025", "6th Aug 2025".
        $textualFormats = [
            'j F Y', 'd F Y', 'j M Y', 'd M Y',
            'jS F Y', 'dS F Y', 'jS M Y', 'dS M Y',
        ];

        foreach ($textualFormats as $format) {
            $dateObj = \DateTime::createFromFormat($format, $date);
            if ($dateObj) {
                return $dateObj->format('Y-m-d');
            }
        }

        return null;
    }

    /**
     * Suggest a category based on supplier name keyword matching.
     * Categories listed here must match the names in the database.
     */
    private function suggestCategory(?string $supplier, string $categories): ?string
    {
        if (! $supplier) {
            return null;
        }

        $supplierLower = strtolower($supplier);

        $keywords = [
            // Restaurants, pubs, bars, cafés → Meals and Entertainment
            'restaurant' => 'Meals and Entertainment',
            'kitchen' => 'Meals and Entertainment',
            'grill' => 'Meals and Entertainment',
            'steakhouse' => 'Meals and Entertainment',
            'bistro' => 'Meals and Entertainment',
            'diner' => 'Meals and Entertainment',
            'brasserie' => 'Meals and Entertainment',
            'eatery' => 'Meals and Entertainment',
            'pizzeria' => 'Meals and Entertainment',
            'pizza' => 'Meals and Entertainment',
            'burger' => 'Meals and Entertainment',
            'sushi' => 'Meals and Entertainment',
            'noodle' => 'Meals and Entertainment',
            'dumpling' => 'Meals and Entertainment',
            'ramen' => 'Meals and Entertainment',
            'curry' => 'Meals and Entertainment',
            'indian' => 'Meals and Entertainment',
            'thai' => 'Meals and Entertainment',
            'chinese' => 'Meals and Entertainment',
            'italian' => 'Meals and Entertainment',
            'mexican' => 'Meals and Entertainment',
            'tapas' => 'Meals and Entertainment',
            'gastro' => 'Meals and Entertainment',
            'pub' => 'Meals and Entertainment',
            'bar' => 'Meals and Entertainment',
            'tavern' => 'Meals and Entertainment',
            'brewery' => 'Meals and Entertainment',
            'brewhouse' => 'Meals and Entertainment',
            'taproom' => 'Meals and Entertainment',
            'wine bar' => 'Meals and Entertainment',
            'cocktail' => 'Meals and Entertainment',
            'cafe' => 'Meals and Entertainment',
            'café' => 'Meals and Entertainment',
            'coffee' => 'Meals and Entertainment',
            'bakery' => 'Meals and Entertainment',
            'patisserie' => 'Meals and Entertainment',
            'starbucks' => 'Meals and Entertainment',
            'costa' => 'Meals and Entertainment',
            'pret' => 'Meals and Entertainment',
            'nero' => 'Meals and Entertainment',
            'deliveroo' => 'Meals and Entertainment',
            'uber eats' => 'Meals and Entertainment',
            'just eat' => 'Meals and Entertainment',

            // Groceries / retail
            'tesco' => 'Sundry Expenses',
            'sainsbury' => 'Sundry Expenses',
            'waitrose' => 'Sundry Expenses',
            'lidl' => 'Sundry Expenses',
            'aldi' => 'Sundry Expenses',
            'asda' => 'Sundry Expenses',
            'morrisons' => 'Sundry Expenses',
            'm&s' => 'Sundry Expenses',
            'marks & spencer' => 'Sundry Expenses',
            'whole foods' => 'Sundry Expenses',
            'co-op' => 'Sundry Expenses',

            // Office supplies / equipment
            'amazon' => 'Equipment',
            'apple' => 'Equipment',
            'argos' => 'Equipment',
            'currys' => 'Equipment',
            'pc world' => 'Equipment',
            'john lewis' => 'Equipment',

            // Software
            'google' => 'Software',
            'microsoft' => 'Software',
            'github' => 'Software',
            'gitlab' => 'Software',
            'digitalocean' => 'Software',
            'aws' => 'Software',
            'linode' => 'Software',
            'cloudflare' => 'Software',
            'namecheap' => 'Software',
            'vultr' => 'Software',
            'hetzner' => 'Software',
            'ovh' => 'Software',
            'krystal' => 'Software',

            // Travel
            'shell' => 'Travel',
            'bp' => 'Travel',
            'esso' => 'Travel',
            'texaco' => 'Travel',
            'national rail' => 'Travel',
            'trainline' => 'Travel',
            'uber' => 'Travel',
            'bolt' => 'Travel',
            'tfl' => 'Travel',
            'transport for london' => 'Travel',
            'national express' => 'Travel',
            'megabus' => 'Travel',
            'ryanair' => 'Travel',
            'easyjet' => 'Travel',
            'ba' => 'Travel',
            'british airways' => 'Travel',
            'heathrow' => 'Travel',
            'gatwick' => 'Travel',
            'luton' => 'Travel',
            'stansted' => 'Travel',
            'eurostar' => 'Travel',
            'airline' => 'Travel',

            // Accommodation
            'hotel' => 'Accomodation',
            'premier inn' => 'Accomodation',
            'travelodge' => 'Accomodation',
            'airbnb' => 'Accomodation',
            'booking.com' => 'Accomodation',
            'hilton' => 'Accomodation',
            'marriott' => 'Accomodation',
            'holiday inn' => 'Accomodation',
            'ibis' => 'Accomodation',
            'novotel' => 'Accomodation',

            // Professional Services
            'accountant' => 'Professional Services',
            'solicitor' => 'Professional Services',
            'lawyer' => 'Professional Services',
            'legal' => 'Professional Services',
            'consult' => 'Professional Services',
            'surveyor' => 'Professional Services',
        ];

        foreach ($keywords as $keyword => $category) {
            if (str_contains($supplierLower, $keyword)) {
                return $category;
            }
        }

        return null;
    }
}
