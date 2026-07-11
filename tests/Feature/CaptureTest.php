<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Jobs\ExtractCapturePreview;
use App\Models\BusinessUnit;
use App\Models\Category;
use App\Models\Expense;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CaptureTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Category $category;

    private BusinessUnit $businessUnit;

    private PaymentMethod $paymentMethod;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->user = User::factory()->create();
        $this->category = Category::factory()->create(['type' => TransactionType::Expense]);
        $this->businessUnit = BusinessUnit::factory()->create();
        $this->paymentMethod = PaymentMethod::factory()->create();
    }

    public function test_capture_photo_stashes_the_file_and_queues_ocr(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->user)->post(
            '/capture',
            [
                'photo' => UploadedFile::fake()->image('receipt.jpg'),
            ]
        );

        $response->assertRedirect();
        $this->assertStringContainsString('/capture/', $response->headers->get('Location'));

        $token = basename($response->headers->get('Location'));
        $pending = Cache::get("capture:{$token}");

        $this->assertSame('processing', $pending['status']);
        $this->assertTrue(Storage::exists($pending['path']));
        Queue::assertPushed(ExtractCapturePreview::class, fn ($job) => $job->token === $token);
    }

    public function test_confirm_redirects_to_capture_create_for_an_unknown_token(): void
    {
        $this->actingAs($this->user)
            ->get('/capture/does-not-exist')
            ->assertRedirect(route('capture.create'));
    }

    public function test_status_endpoint_reports_cache_state(): void
    {
        Cache::put('capture:tok123', ['status' => 'processing', 'path' => 'receipts/pending/tok123.jpg']);

        $this->actingAs($this->user)
            ->get('/capture/tok123/status')
            ->assertOk()
            ->assertJson(['status' => 'processing']);
    }

    public function test_status_endpoint_reports_expired_for_unknown_token(): void
    {
        $this->actingAs($this->user)
            ->get('/capture/unknown/status')
            ->assertOk()
            ->assertJson(['status' => 'expired']);
    }

    public function test_expenses_create_prefills_from_a_ready_capture(): void
    {
        Cache::put(
            'capture:tok123',
            [
                'status' => 'ready',
                'path' => 'receipts/pending/tok123.jpg',
                'original_filename' => 'receipt.jpg',
                'content_type' => 'image/jpeg',
                'byte_size' => 1234,
                'ocr' => [
                    'date' => '2026-06-14',
                    'amount' => '12.99',
                    'vat' => '2.60',
                    'currency' => 'GBP',
                    'supplier' => 'Krystal',
                    'suggested_category' => $this->category->name,
                    'raw_text' => 'Krystal ...',
                ],
            ]
        );

        $response = $this->actingAs($this->user)->get('/expenses/create?capture_token=tok123');

        $response->assertOk();
        $response->assertSee('Krystal');
        $response->assertSee('12.99');
        $response->assertSee('2.60');
    }

    public function test_saving_attaches_the_captured_receipt_to_the_new_expense(): void
    {
        $path = UploadedFile::fake()->image('receipt.jpg')->storeAs('receipts/pending', 'tok123.jpg');

        Cache::put(
            'capture:tok123',
            [
                'status' => 'ready',
                'path' => $path,
                'original_filename' => 'receipt.jpg',
                'content_type' => 'image/jpeg',
                'byte_size' => 1234,
                'ocr' => ['supplier' => 'Krystal', 'raw_text' => 'Krystal ...'],
            ]
        );

        $this->actingAs($this->user)->post(
            '/expenses',
            [
                'occurred_on' => '2026-06-14',
                'counterparty' => 'Krystal',
                'description' => 'Hosting invoice',
                'amount' => '12.99',
                'currency' => 'GBP',
                'category_id' => $this->category->id,
                'business_unit_id' => $this->businessUnit->id,
                'payment_method_id' => $this->paymentMethod->id,
                'capture_token' => 'tok123',
            ]
        )->assertRedirect(route('expenses.index'));

        $expense = Expense::where('counterparty', 'Krystal')->firstOrFail();

        $this->assertSame(1, $expense->receipts()->count());
        $receipt = $expense->receipts()->first();
        $this->assertSame('Krystal', $receipt->ocr_json['supplier']);
        $this->assertFalse(Storage::exists($path));
        $this->assertTrue(Storage::exists($receipt->path));
        $this->assertNull(Cache::get('capture:tok123'));
    }

    public function test_saving_without_a_capture_token_does_not_attach_a_receipt(): void
    {
        $this->actingAs($this->user)->post(
            '/expenses',
            [
                'occurred_on' => '2026-06-14',
                'counterparty' => 'Krystal',
                'description' => 'Hosting invoice',
                'amount' => '12.99',
                'currency' => 'GBP',
                'category_id' => $this->category->id,
                'business_unit_id' => $this->businessUnit->id,
                'payment_method_id' => $this->paymentMethod->id,
            ]
        )->assertRedirect(route('expenses.index'));

        $expense = Expense::where('counterparty', 'Krystal')->firstOrFail();
        $this->assertSame(0, $expense->receipts()->count());
    }
}
