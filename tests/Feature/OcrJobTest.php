<?php

namespace Tests\Feature;

use App\Jobs\ExtractReceiptData;
use App\Models\Expense;
use App\Models\Receipt;
use App\Models\User;
use App\Services\ReceiptOcr;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OcrJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function makeReceipt(): Receipt
    {
        $expense = Expense::factory()->create();
        $path = UploadedFile::fake()->image('receipt.jpg')->store("receipts/{$expense->id}");

        return $expense->receipts()->create(
            [
                'path' => $path,
                'original_filename' => 'receipt.jpg',
                'content_type' => 'image/jpeg',
            ]
        );
    }

    /**
     * Stub the OCR service's engine seam and run the real job so the
     * parser and persistence are exercised end to end.
     */
    private function runJobWithDetectedText(Receipt $receipt, string $text): void
    {
        $ocr = \Mockery::mock(ReceiptOcr::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $ocr->shouldReceive('detectText')->once()->andReturn($text);

        (new ExtractReceiptData($receipt))->handle($ocr);
    }

    public function test_job_stores_extracted_fields_on_receipt_and_expense(): void
    {
        $receipt = $this->makeReceipt();

        $this->runJobWithDetectedText($receipt, "Tesco\n£12.99\n14/06/2026");

        $receipt->refresh();
        $this->assertSame('Tesco', $receipt->ocr_json['supplier']);
        $this->assertSame('12.99', $receipt->ocr_json['amount']);
        $this->assertSame('2026-06-14', $receipt->transaction->fresh()->ocr_json['date']);
    }

    public function test_job_extracts_vat_alongside_amount(): void
    {
        $receipt = $this->makeReceipt();

        $this->runJobWithDetectedText($receipt, "Tesco\nSubtotal 10.00\nVAT 2.00\nTotal 12.00\n14/06/2026");

        $receipt->refresh();
        $this->assertSame('2.00', $receipt->ocr_json['vat']);
    }

    public function test_amount_prefers_total_line_over_a_reference_number(): void
    {
        $receipt = $this->makeReceipt();

        $this->runJobWithDetectedText(
            $receipt,
            "Wood Wharf\n* DRAFT RECEIPT #1 *\nFiltered Still Water 2.00\nAmount due 15.00\n06/08/2025"
        );

        $receipt->refresh();
        $this->assertSame('15.00', $receipt->ocr_json['amount']);
    }

    public function test_amount_ignores_a_bare_reference_number_with_no_decimal(): void
    {
        $receipt = $this->makeReceipt();

        $this->runJobWithDetectedText(
            $receipt,
            "Cafe\nReceipt #4521, Table 3\nFlat White 3.20\n01/02/2026"
        );

        $receipt->refresh();
        $this->assertSame('3.20', $receipt->ocr_json['amount']);
    }

    public function test_job_fails_when_no_text_is_detected(): void
    {
        $receipt = $this->makeReceipt();

        $this->runJobWithDetectedText($receipt, '');

        $this->assertNull($receipt->fresh()->ocr_json);
    }

    public function test_ocr_errors_propagate_so_the_queue_can_retry(): void
    {
        // The job must NOT swallow engine errors into an immediate fail():
        // a thrown exception is what makes the worker retry with the
        // configured ocr.tries/backoff before landing in failed_jobs.
        $receipt = $this->makeReceipt();

        $ocr = \Mockery::mock(ReceiptOcr::class);
        $ocr->shouldReceive('extract')->once()->andThrow(new \RuntimeException('tesseract crashed'));

        $this->expectException(\RuntimeException::class);

        (new ExtractReceiptData($receipt))->handle($ocr);
    }

    public function test_uploading_a_receipt_queues_the_ocr_job(): void
    {
        config(['services.google_vision.credentials' => 'test-credentials']);
        Queue::fake();

        $expense = Expense::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(
                "/expenses/{$expense->id}/receipts",
                [
                    'receipts' => [UploadedFile::fake()->image('receipt.jpg')],
                ]
            );

        Queue::assertPushed(ExtractReceiptData::class);
    }

    public function test_job_is_still_queued_without_vision_credentials(): void
    {
        config(['services.google_vision.credentials' => null]);
        Queue::fake();

        $expense = Expense::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(
                "/expenses/{$expense->id}/receipts",
                [
                    'receipts' => [UploadedFile::fake()->image('receipt.jpg')],
                ]
            );

        // No Vision credentials configured; the job still runs since it
        // falls back to the local Tesseract binary.
        Queue::assertPushed(ExtractReceiptData::class);
    }

    public function test_falls_back_to_tesseract_when_no_vision_credentials(): void
    {
        config(['services.google_vision.credentials' => null]);
        $receipt = $this->makeReceipt();

        $ocr = \Mockery::mock(ReceiptOcr::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $ocr->shouldNotReceive('detectTextViaVision');
        $ocr->shouldReceive('detectTextViaTesseract')->once()->andReturn("Costa\n£3.20\n01/02/2026");

        (new ExtractReceiptData($receipt))->handle($ocr);

        $receipt->refresh();
        $this->assertSame('Costa', $receipt->ocr_json['supplier']);
    }

    public function test_falls_back_to_tesseract_when_vision_call_errors(): void
    {
        config(['services.google_vision.credentials' => 'test-credentials']);
        $receipt = $this->makeReceipt();

        $ocr = \Mockery::mock(ReceiptOcr::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $ocr->shouldReceive('detectTextViaVision')->once()->andThrow(new \Exception('expired key'));
        $ocr->shouldReceive('detectTextViaTesseract')->once()->andReturn("Costa\n£3.20\n01/02/2026");

        (new ExtractReceiptData($receipt))->handle($ocr);

        $receipt->refresh();
        $this->assertSame('Costa', $receipt->ocr_json['supplier']);
    }

    // --- Supplier extraction improvements ---

    public function test_supplier_does_not_glue_a_short_name_to_an_unrelated_next_line(): void
    {
        $receipt = $this->makeReceipt();

        $this->runJobWithDetectedText(
            $receipt,
            "Tesco\nEvery Little Helps\nMilk 1.00\nTotal 3.50\n06/08/2025"
        );

        $receipt->refresh();
        $this->assertSame('Tesco', $receipt->ocr_json['supplier']);
    }

    public function test_supplier_keeps_co_op_intact(): void
    {
        $receipt = $this->makeReceipt();

        $this->runJobWithDetectedText(
            $receipt,
            "Co-op\nHigh Street\nBread 1.20\nTotal 5.40\n06/08/2025"
        );

        $receipt->refresh();
        $this->assertSame('Co-op', $receipt->ocr_json['supplier']);
    }

    public function test_supplier_skips_address_line_with_street_number(): void
    {
        $receipt = $this->makeReceipt();

        $this->runJobWithDetectedText(
            $receipt,
            "The Grill\n10 Canal Reach\nFiltered Still Water 2.00\nTotal 15.00\n06/08/2025"
        );

        $receipt->refresh();
        $this->assertSame('The Grill', $receipt->ocr_json['supplier']);
    }

    public function test_supplier_skips_uk_postcode_line(): void
    {
        $receipt = $this->makeReceipt();

        $this->runJobWithDetectedText(
            $receipt,
            "N1C 4AL\nThe Grill\nFiltered Still Water 2.00\nTotal 15.00\n06/08/2025"
        );

        $receipt->refresh();
        $this->assertSame('The Grill', $receipt->ocr_json['supplier']);
    }

    public function test_supplier_skips_phone_number(): void
    {
        $receipt = $this->makeReceipt();

        $this->runJobWithDetectedText(
            $receipt,
            "The Grill\n020 1234 5678\nFiltered Still Water 2.00\nTotal 15.00\n06/08/2025"
        );

        $receipt->refresh();
        $this->assertSame('The Grill', $receipt->ocr_json['supplier']);
    }

    public function test_supplier_skips_url_line(): void
    {
        $receipt = $this->makeReceipt();

        $this->runJobWithDetectedText(
            $receipt,
            "The Grill\nwww.thegrill.com\nFiltered Still Water 2.00\nTotal 15.00\n06/08/2025"
        );

        $receipt->refresh();
        $this->assertSame('The Grill', $receipt->ocr_json['supplier']);
    }

    public function test_supplier_skips_vat_registration_line(): void
    {
        $receipt = $this->makeReceipt();

        $this->runJobWithDetectedText(
            $receipt,
            "VAT No: 123 4567 89\nThe Grill\nFiltered Still Water 2.00\nTotal 15.00\n06/08/2025"
        );

        $receipt->refresh();
        $this->assertSame('The Grill', $receipt->ocr_json['supplier']);
    }

    public function test_supplier_skips_receipt_header(): void
    {
        $receipt = $this->makeReceipt();

        $this->runJobWithDetectedText(
            $receipt,
            "Receipt\nDRAFT RECEIPT\nThe Grill\nFiltered Still Water 2.00\nTotal 15.00\n06/08/2025"
        );

        $receipt->refresh();
        $this->assertSame('The Grill', $receipt->ocr_json['supplier']);
    }

    // --- Date extraction improvements ---

    public function test_parses_a_2_digit_year_as_the_2000s_not_literally(): void
    {
        $receipt = $this->makeReceipt();

        $this->runJobWithDetectedText(
            $receipt,
            "Hilton\nCocktail 14.00\nTotal 14.00\n12/06/24 23:17"
        );

        $receipt->refresh();
        $this->assertSame('2024-06-12', $receipt->ocr_json['date']);
    }

    public function test_parses_date_in_d_month_yyyy_format(): void
    {
        $receipt = $this->makeReceipt();

        $this->runJobWithDetectedText(
            $receipt,
            "ROE Wood Wharf\nFiltered Still Water 2.00\nTotal 62.41\n6 August 2025"
        );

        $receipt->refresh();
        $this->assertSame('2025-08-06', $receipt->ocr_json['date']);
    }

    public function test_parses_date_in_ordinal_d_mon_yyyy_format(): void
    {
        $receipt = $this->makeReceipt();

        $this->runJobWithDetectedText(
            $receipt,
            "ROE Wood Wharf\nFiltered Still Water 2.00\nTotal 62.41\n6th Aug 2025"
        );

        $receipt->refresh();
        $this->assertSame('2025-08-06', $receipt->ocr_json['date']);
    }

    public function test_parses_date_in_d_monthname_year_format(): void
    {
        $receipt = $this->makeReceipt();

        $this->runJobWithDetectedText(
            $receipt,
            "ROE Wood Wharf\nFiltered Still Water 2.00\nTotal 62.41\n14 June 2026"
        );

        $receipt->refresh();
        $this->assertSame('2026-06-14', $receipt->ocr_json['date']);
    }

    // --- Amount extraction improvements ---

    public function test_amount_prefers_largest_when_no_total_line(): void
    {
        $receipt = $this->makeReceipt();

        $this->runJobWithDetectedText(
            $receipt,
            "ROE Wood Wharf\nFiltered Still Water 2.00\nBurger 12.50\nGlass of Wine 8.00\n06/08/2025"
        );

        $receipt->refresh();
        $this->assertSame('12.50', $receipt->ocr_json['amount']);
    }

    public function test_amount_prefers_a_repeated_value_over_a_larger_outlier(): void
    {
        $receipt = $this->makeReceipt();

        $this->runJobWithDetectedText(
            $receipt,
            "Hilton\nCocktail 14.00\nElectronic Payment 14.00\nTVA 16.60\nAmount Paid 14.0u\n12/06/24"
        );

        $receipt->refresh();
        $this->assertSame('14.00', $receipt->ocr_json['amount']);
    }

    public function test_amount_uses_card_payment_line(): void
    {
        $receipt = $this->makeReceipt();

        $this->runJobWithDetectedText(
            $receipt,
            "The Grill\nFiltered Still Water 2.00\nBurger 12.50\nCard Payment 14.50\n06/08/2025"
        );

        $receipt->refresh();
        $this->assertSame('14.50', $receipt->ocr_json['amount']);
    }

    public function test_amount_uses_grand_total_line(): void
    {
        $receipt = $this->makeReceipt();

        $this->runJobWithDetectedText(
            $receipt,
            "The Grill\nFiltered Still Water 2.00\nSub-total 13.00\nVAT 2.60\nGrand Total 15.60\n06/08/2025"
        );

        $receipt->refresh();
        $this->assertSame('15.60', $receipt->ocr_json['amount']);
    }

    public function test_amount_prefers_total_over_largest_line_item(): void
    {
        $receipt = $this->makeReceipt();

        $this->runJobWithDetectedText(
            $receipt,
            "ROE Wood Wharf\nSteak 28.50\nLobster 34.00\nTotal 62.41\n6 August 2025"
        );

        $receipt->refresh();
        $this->assertSame('62.41', $receipt->ocr_json['amount']);
    }

    // --- VAT extraction improvements ---

    public function test_vat_from_next_line_when_label_is_alone(): void
    {
        $receipt = $this->makeReceipt();

        $this->runJobWithDetectedText(
            $receipt,
            "ROE Wood Wharf\nFiltered Still Water 2.00\nSubtotal 12.00\nVAT\n2.40\nTotal 14.40\n06/08/2025"
        );

        $receipt->refresh();
        $this->assertSame('2.40', $receipt->ocr_json['vat']);
    }

    public function test_vat_does_not_steal_the_total_when_no_value_follows(): void
    {
        $receipt = $this->makeReceipt();

        $this->runJobWithDetectedText(
            $receipt,
            "The Grill\nFiltered Still Water 2.00\nSub-total 50.00\nVAT\nTotal 62.41\n06/08/2025"
        );

        $receipt->refresh();
        $this->assertNull($receipt->ocr_json['vat']);
        $this->assertSame('62.41', $receipt->ocr_json['amount']);
    }

    public function test_vat_still_works_on_same_line(): void
    {
        $receipt = $this->makeReceipt();

        $this->runJobWithDetectedText(
            $receipt,
            "ROE Wood Wharf\nFiltered Still Water 2.00\nVAT 2.40\nTotal 14.40\n06/08/2025"
        );

        $receipt->refresh();
        $this->assertSame('2.40', $receipt->ocr_json['vat']);
    }

    // --- Category suggestion improvements ---

    public function test_suggests_meals_for_restaurant_keyword(): void
    {
        $receipt = $this->makeReceipt();

        $this->runJobWithDetectedText(
            $receipt,
            "The Grill\nFiltered Still Water 2.00\nTotal 15.00\n06/08/2025"
        );

        $receipt->refresh();
        $this->assertSame('Meals and Entertainment', $receipt->ocr_json['suggested_category']);
    }

    public function test_suggests_meals_for_pub(): void
    {
        $receipt = $this->makeReceipt();

        $this->runJobWithDetectedText(
            $receipt,
            "The Red Lion Pub\nFiltered Still Water 2.00\nTotal 15.00\n06/08/2025"
        );

        $receipt->refresh();
        $this->assertSame('Meals and Entertainment', $receipt->ocr_json['suggested_category']);
    }

    public function test_suggests_meals_for_brewery(): void
    {
        $receipt = $this->makeReceipt();

        $this->runJobWithDetectedText(
            $receipt,
            "Camden Brewery\nFiltered Still Water 2.00\nTotal 15.00\n06/08/2025"
        );

        $receipt->refresh();
        $this->assertSame('Meals and Entertainment', $receipt->ocr_json['suggested_category']);
    }

    public function test_suggests_equipment_for_amazon(): void
    {
        $receipt = $this->makeReceipt();

        $this->runJobWithDetectedText(
            $receipt,
            "Amazon\nKeyboard 45.00\nTotal 45.00\n06/08/2025"
        );

        $receipt->refresh();
        $this->assertSame('Equipment', $receipt->ocr_json['suggested_category']);
    }

    public function test_suggests_software_for_github(): void
    {
        $receipt = $this->makeReceipt();

        $this->runJobWithDetectedText(
            $receipt,
            "GitHub\nPro plan 7.00\nTotal 7.00\n06/08/2025"
        );

        $receipt->refresh();
        $this->assertSame('Software', $receipt->ocr_json['suggested_category']);
    }

    public function test_suggests_travel_for_tfl(): void
    {
        $receipt = $this->makeReceipt();

        $this->runJobWithDetectedText(
            $receipt,
            "TfL\nPeak cap 2.80\nTotal 2.80\n06/08/2025"
        );

        $receipt->refresh();
        $this->assertSame('Travel', $receipt->ocr_json['suggested_category']);
    }
}
