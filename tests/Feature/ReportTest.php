<?php

namespace Tests\Feature;

use App\Models\AccountBalance;
use App\Models\CashFlow;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Income;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_reports_show_yearly_income_expense_profit_and_cf(): void
    {
        Income::factory()->create(['occurred_on' => '2025-03-01', 'amount_gbp_minor' => 50000]);
        Expense::factory()->create(['occurred_on' => '2025-06-01', 'amount_gbp_minor' => 20000]);
        CashFlow::factory()->create(['occurred_on' => '2025-01-15', 'amount_gbp_minor' => 10000]);

        $this->actingAs($this->user)
            ->get('/reports')
            ->assertOk()
            ->assertSee('£500.00')  // income
            ->assertSee('£200.00')  // expense
            ->assertSee('£300.00')  // profit
            ->assertSee('£100.00'); // cash flow
    }

    public function test_expenses_group_under_report_group(): void
    {
        $category = Category::factory()->create(
            [
                'name' => 'Website Expenses',
                'report_group' => 'Advertising and PR',
            ]
        );
        Expense::factory()->create(
            [
                'occurred_on' => '2025-06-01',
                'category_id' => $category->id,
                'amount_gbp_minor' => 7700,
            ]
        );

        $this->actingAs($this->user)
            ->get('/reports')
            ->assertOk()
            ->assertSee('Advertising and PR')
            ->assertSee('Website Expenses');
    }

    public function test_personal_card_spend_is_subtotalled(): void
    {
        $personal = PaymentMethod::factory()->create(['name' => 'Amex BA', 'is_personal' => true]);
        Expense::factory()->create(
            [
                'occurred_on' => '2025-06-01',
                'payment_method_id' => $personal->id,
                'amount_gbp_minor' => 5500,
            ]
        );

        $this->actingAs($this->user)
            ->get('/reports')
            ->assertOk()
            ->assertSee('personal cards');
    }

    public function test_balances_page_records_and_pivots_snapshots(): void
    {
        $this->actingAs($this->user)->post(
            '/balances', [
                'account' => 'Wise',
                'currency' => 'EUR',
                'balance_on' => '2025-12-31',
                'balance' => '196.46',
                'notes' => 'Account opened Feb 2025',
            ]
        )->assertRedirect('/balances');

        $this->assertDatabaseHas(
            'account_balances', [
                'account' => 'Wise',
                'currency' => 'EUR',
                'balance_minor' => 19646,
            ]
        );

        // Re-posting the same account+currency+date replaces, not duplicates.
        $this->actingAs($this->user)->post(
            '/balances', [
                'account' => 'Wise',
                'currency' => 'EUR',
                'balance_on' => '2025-12-31',
                'balance' => '200.00',
            ]
        );

        $this->assertSame(1, AccountBalance::count());
        $this->assertSame(20000, AccountBalance::first()->balance_minor);

        $this->actingAs($this->user)
            ->get('/balances')
            ->assertOk()
            ->assertSee('Wise')
            ->assertSee('200.00');
    }

    public function test_ledger_shows_running_balance_in_original_currency(): void
    {
        $paypal = PaymentMethod::factory()->create(['name' => 'PayPal']);

        // Mirrors the tracker's PayPal USD sheet: two payouts in, one
        // subscription out.
        Income::factory()->create(
            [
                'occurred_on' => '2024-09-10',
                'payment_method_id' => $paypal->id,
                'currency' => 'USD',
                'amount_minor' => 12654,
                'vat_minor' => 0,
            ]
        );
        Income::factory()->create(
            [
                'occurred_on' => '2024-11-10',
                'payment_method_id' => $paypal->id,
                'currency' => 'USD',
                'amount_minor' => 33348,
                'vat_minor' => 0,
            ]
        );
        Expense::factory()->create(
            [
                'occurred_on' => '2025-12-15',
                'payment_method_id' => $paypal->id,
                'currency' => 'USD',
                'amount_minor' => 7500,
                'vat_minor' => 0,
                'counterparty' => 'Bloomberg LP',
            ]
        );

        $response = $this->actingAs($this->user)
            ->get('/ledger?payment_method_id='.$paypal->id.'&currency=USD')
            ->assertOk()
            ->assertSee('126.54')   // first payout
            ->assertSee('460.02')   // 126.54 + 333.48
            ->assertSee('-75.00')   // Bloomberg out
            ->assertSee('385.02');  // closing balance

        // GBP view of the same method is empty — currency isolation.
        $this->actingAs($this->user)
            ->get('/ledger?payment_method_id='.$paypal->id.'&currency=GBP')
            ->assertOk()
            ->assertSee('No GBP transactions');
    }
}
