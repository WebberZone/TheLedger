<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Models\BusinessUnit;
use App\Models\CashFlow;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Income;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Category $category;

    private Category $incomeCategory;

    private Category $cashFlowCategory;

    private BusinessUnit $businessUnit;

    private PaymentMethod $paymentMethod;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->category = Category::factory()->create(['type' => TransactionType::Expense]);
        $this->incomeCategory = Category::factory()->create(['type' => TransactionType::Income]);
        $this->cashFlowCategory = Category::factory()->create(['type' => TransactionType::CashFlow]);
        $this->businessUnit = BusinessUnit::factory()->create();
        $this->paymentMethod = PaymentMethod::factory()->create();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/expenses')->assertRedirect('/login');
        $this->get('/income')->assertRedirect('/login');
        $this->get('/cashflows')->assertRedirect('/login');
    }

    public function test_each_ledger_lists_only_its_own_rows(): void
    {
        Expense::factory()->create(['counterparty' => 'Krystal Hosting']);
        Income::factory()->create(['counterparty' => 'Freemius Payout']);
        CashFlow::factory()->create(['counterparty' => 'Director Loan In']);

        $this->actingAs($this->user)->get('/expenses')
            ->assertOk()
            ->assertSee('Krystal Hosting')
            ->assertDontSee('Freemius Payout')
            ->assertDontSee('Director Loan In');

        $this->actingAs($this->user)->get('/income')
            ->assertOk()
            ->assertSee('Freemius Payout')
            ->assertDontSee('Krystal Hosting');

        $this->actingAs($this->user)->get('/cashflows')
            ->assertOk()
            ->assertSee('Director Loan In')
            ->assertDontSee('Krystal Hosting');
    }

    public function test_gbp_expense_with_vat_computes_gross(): void
    {
        $response = $this->actingAs($this->user)->post(
            '/expenses', [
                'occurred_on' => '2026-06-01',
                'counterparty' => 'Desktronic',
                'description' => 'Standing desk',
                'amount' => '71.97',
                'vat' => '14.39',
                'currency' => 'GBP',
                'category_id' => $this->category->id,
                'business_unit_id' => $this->businessUnit->id,
                'payment_method_id' => $this->paymentMethod->id,
            ]
        );

        $response->assertRedirect('/expenses');

        // Gross 71.97 + 14.39 = 86.36 — matches the tracker's maths.
        $this->assertDatabaseHas(
            'transactions', [
                'type' => 'expense',
                'counterparty' => 'Desktronic',
                'amount_minor' => 7197,
                'vat_minor' => 1439,
                'amount_gbp_minor' => 8636,
            ]
        );
    }

    public function test_usd_expense_converts_via_rate(): void
    {
        $this->actingAs($this->user)->post(
            '/expenses', [
                'occurred_on' => '2026-06-01',
                'counterparty' => 'Freemius',
                'description' => 'Plugin payout',
                'amount' => '100.00',
                'currency' => 'USD',
                'fx_rate' => '0.79',
                'category_id' => $this->category->id,
                'business_unit_id' => $this->businessUnit->id,
                'payment_method_id' => $this->paymentMethod->id,
            ]
        );

        $this->assertDatabaseHas(
            'transactions', [
                'counterparty' => 'Freemius',
                'amount_minor' => 10000,
                'amount_gbp_minor' => 7900,
                'currency' => 'USD',
            ]
        );
    }

    public function test_usd_expense_derives_rate_from_actual_gbp_charged(): void
    {
        // The statement says £6.76 for an $8.03 charge — the rate is
        // derived, like the FX column in the tracker spreadsheet.
        $this->actingAs($this->user)->post(
            '/expenses', [
                'occurred_on' => '2026-06-01',
                'counterparty' => 'CloudFlare',
                'description' => 'CDN renewal',
                'amount' => '8.03',
                'currency' => 'USD',
                'amount_gbp' => '6.76',
                'category_id' => $this->category->id,
                'business_unit_id' => $this->businessUnit->id,
                'payment_method_id' => $this->paymentMethod->id,
            ]
        );

        $this->assertDatabaseHas(
            'transactions', [
                'counterparty' => 'CloudFlare',
                'amount_minor' => 803,
                'amount_gbp_minor' => 676,
            ]
        );

        $expense = Expense::where('counterparty', 'CloudFlare')->first();
        $this->assertEqualsWithDelta(0.8418, (float) $expense->fx_rate, 0.001);
    }

    public function test_non_gbp_requires_rate_or_gbp_amount(): void
    {
        $response = $this->actingAs($this->user)->post(
            '/expenses', [
                'occurred_on' => '2026-06-01',
                'counterparty' => 'Freemius',
                'description' => 'Plugin payout',
                'amount' => '100.00',
                'currency' => 'USD',
                'category_id' => $this->category->id,
                'business_unit_id' => $this->businessUnit->id,
                'payment_method_id' => $this->paymentMethod->id,
            ]
        );

        $response->assertSessionHasErrors('fx_rate');
    }

    public function test_income_can_be_created(): void
    {
        $this->actingAs($this->user)->post(
            '/income', [
                'occurred_on' => '2026-02-10',
                'counterparty' => 'Freemius',
                'description' => 'Payout for Dec 2025',
                'amount' => '302.99',
                'currency' => 'USD',
                'fx_rate' => '0.731877',
                'category_id' => $this->incomeCategory->id,
                'business_unit_id' => $this->businessUnit->id,
                'payment_method_id' => $this->paymentMethod->id,
            ]
        );

        $this->assertDatabaseHas(
            'transactions', [
                'type' => 'income',
                'counterparty' => 'Freemius',
                'amount_minor' => 30299,
                'amount_gbp_minor' => 22175, // round(30299 * 0.731877)
            ]
        );
    }

    public function test_notification_uses_description_when_present(): void
    {
        $this->actingAs($this->user)->post(
            '/income', [
                'occurred_on' => '2026-02-10',
                'counterparty' => 'Freemius',
                'description' => 'Payout for Dec 2025',
                'amount' => '302.99',
                'currency' => 'USD',
                'fx_rate' => '0.731877',
                'category_id' => $this->incomeCategory->id,
                'business_unit_id' => $this->businessUnit->id,
                'payment_method_id' => $this->paymentMethod->id,
            ]
        )->assertRedirect('/income')
            ->assertSessionHas(
                'status',
                fn (string $value) => str_contains($value, 'Transaction saved: Payout for Dec 2025')
            );
    }

    public function test_cash_flow_can_be_created(): void
    {
        $this->actingAs($this->user)->post(
            '/cashflows', [
                'occurred_on' => '2023-12-21',
                'counterparty' => "Ajay D'Souza",
                'description' => 'Director loan repayment',
                'amount' => '50.00',
                'currency' => 'GBP',
                'category_id' => $this->cashFlowCategory->id,
                'business_unit_id' => $this->businessUnit->id,
                'payment_method_id' => $this->paymentMethod->id,
            ]
        );

        $this->assertDatabaseHas(
            'transactions', [
                'type' => 'cash_flow',
                'amount_minor' => 5000,
            ]
        );
    }

    public function test_wrong_ledger_404s_on_foreign_ids(): void
    {
        $income = Income::factory()->create();

        // An income row is invisible to the expenses routes.
        $this->actingAs($this->user)
            ->get("/expenses/{$income->id}")
            ->assertNotFound();
    }

    public function test_transaction_can_be_updated(): void
    {
        $expense = Expense::factory()->create();

        $this->actingAs($this->user)->patch(
            "/expenses/{$expense->id}", [
                'occurred_on' => '2026-05-15',
                'counterparty' => 'Updated Supplier',
                'description' => 'Updated purchase',
                'amount' => '50.00',
                'currency' => 'GBP',
                'category_id' => $this->category->id,
                'business_unit_id' => $this->businessUnit->id,
                'payment_method_id' => $this->paymentMethod->id,
            ]
        )->assertRedirect('/expenses');

        $this->assertDatabaseHas(
            'transactions', [
                'id' => $expense->id,
                'counterparty' => 'Updated Supplier',
                'amount_minor' => 5000,
            ]
        );
    }

    public function test_deleting_removes_the_row(): void
    {
        $expense = Expense::factory()->create();

        $this->actingAs($this->user)->delete("/expenses/{$expense->id}");

        // Deliberate hard delete — soft deletes were removed by design.
        $this->assertDatabaseMissing('transactions', ['id' => $expense->id]);
    }

    public function test_list_filters_by_date_range_and_unit(): void
    {
        $unit = BusinessUnit::factory()->create();
        Expense::factory()->create(
            [
                'counterparty' => 'In Range',
                'occurred_on' => '2026-06-15',
                'business_unit_id' => $unit->id,
            ]
        );
        Expense::factory()->create(['counterparty' => 'Out Of Range', 'occurred_on' => '2025-01-01']);

        $this->actingAs($this->user)
            ->get('/expenses?from=2026-06-01&to=2026-06-30&business_unit_id='.$unit->id)
            ->assertOk()
            ->assertSee('In Range')
            ->assertDontSee('Out Of Range');
    }

    public function test_search_matches_counterparty_description_and_notes(): void
    {
        Expense::factory()->create(['counterparty' => 'CloudFlare', 'description' => null, 'notes' => null]);
        Expense::factory()->create(['counterparty' => 'Krystal', 'description' => 'webberzone.com renewal', 'notes' => null]);
        Expense::factory()->create(['counterparty' => 'Amazon', 'description' => null, 'notes' => 'standing desk drawers']);

        $this->actingAs($this->user)->get('/expenses?q=cloudflare')
            ->assertOk()->assertSee('CloudFlare')->assertDontSee('Krystal');

        $this->actingAs($this->user)->get('/expenses?q=webberzone')
            ->assertOk()->assertSee('Krystal')->assertDontSee('CloudFlare');

        $this->actingAs($this->user)->get('/expenses?q=standing+desk')
            ->assertOk()->assertSee('Amazon')->assertDontSee('Krystal');

        // Search composes with the other filters and the export.
        $csv = $this->actingAs($this->user)->get('/expenses/export?q=webberzone')->streamedContent();
        $this->assertStringContainsString('Krystal', $csv);
        $this->assertStringNotContainsString('CloudFlare', $csv);
    }

    public function test_search_treats_like_wildcards_as_literal_text(): void
    {
        // % and _ are LIKE wildcards; a search term containing them must
        // match them literally (needs an explicit ESCAPE clause on SQLite).
        Expense::factory()->create(['counterparty' => '100% Cotton Ltd', 'description' => null, 'notes' => null]);
        Expense::factory()->create(['counterparty' => '100x Cotton', 'description' => null, 'notes' => null]);

        $this->actingAs($this->user)->get('/expenses?q=100%25+Cotton')
            ->assertOk()->assertSee('100% Cotton Ltd')->assertDontSee('100x Cotton');
    }

    public function test_csv_export_includes_report_group_and_vat(): void
    {
        $category = Category::factory()->create(
            [
                'account_code' => '7500',
                'report_group' => 'Advertising and PR',
            ]
        );
        Expense::factory()->create(
            [
                'counterparty' => 'Csv Supplier',
                'category_id' => $category->id,
                'amount_minor' => 1234,
                'vat_minor' => 246,
                'amount_gbp_minor' => 1480,
            ]
        );

        $response = $this->actingAs($this->user)->get('/expenses/export');

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Csv Supplier', $csv);
        $this->assertStringContainsString('7500', $csv);
        $this->assertStringContainsString('Advertising and PR', $csv);
        $this->assertStringContainsString('2.46', $csv);   // VAT
        $this->assertStringContainsString('14.80', $csv);  // GBP total
    }
}
