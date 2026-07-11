<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReceiptTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        // Swaps the local disk for an in-memory fake — nothing touches
        // the real storage directory during tests.
        Storage::fake('local');
        $this->user = User::factory()->create();
    }

    public function test_a_receipt_can_be_uploaded(): void
    {
        // These tests cover the upload flow, not OCR — fake the queue so
        // the job doesn't run inline (sync driver) against real Tesseract,
        // which would throw on a blank fake image now that OCR errors
        // propagate for the queue's retry handling.
        Queue::fake();
        $expense = Expense::factory()->create();
        $file = UploadedFile::fake()->image('receipt.jpg', 800, 1200);

        $response = $this->actingAs($this->user)->post(
            "/expenses/{$expense->id}/receipts", [
                'receipts' => [$file],
            ]
        );

        $response->assertRedirect();

        $receipt = $expense->receipts()->first();
        $this->assertNotNull($receipt);
        $this->assertSame('receipt.jpg', $receipt->original_filename);
        Storage::assertExists($receipt->path);
    }

    public function test_multiple_receipts_can_be_uploaded_at_once(): void
    {
        Queue::fake();
        $expense = Expense::factory()->create();

        $this->actingAs($this->user)->post(
            "/expenses/{$expense->id}/receipts", [
                'receipts' => [
                    UploadedFile::fake()->image('slip.jpg'),
                    UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf'),
                ],
            ]
        );

        $this->assertSame(2, $expense->receipts()->count());
    }

    public function test_disallowed_file_types_are_rejected(): void
    {
        $expense = Expense::factory()->create();

        $response = $this->actingAs($this->user)->post(
            "/expenses/{$expense->id}/receipts", [
                'receipts' => [UploadedFile::fake()->create('malware.exe', 10)],
            ]
        );

        $response->assertSessionHasErrors('receipts.0');
        $this->assertSame(0, $expense->receipts()->count());
    }

    public function test_receipts_are_served_through_an_authed_route(): void
    {
        $expense = Expense::factory()->create();
        $path = UploadedFile::fake()->image('receipt.jpg')->store("receipts/{$expense->id}");
        $receipt = $expense->receipts()->create(
            [
                'path' => $path,
                'original_filename' => 'receipt.jpg',
                'content_type' => 'image/jpeg',
            ]
        );

        // Guest gets bounced to login, never the file.
        $this->get("/receipts/{$receipt->id}")->assertRedirect('/login');

        $this->actingAs($this->user)
            ->get("/receipts/{$receipt->id}")
            ->assertOk();
    }
}
