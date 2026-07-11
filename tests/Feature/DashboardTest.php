<?php

namespace Tests\Feature;

use App\Models\CashFlow;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Income;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_dashboard_requires_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_root_redirects_to_dashboard(): void
    {
        $this->actingAs($this->user)->get('/')->assertRedirect('/dashboard');
    }

    public function test_dashboard_shows_year_totals_and_profit(): void
    {
        $thisYear = now()->format('Y');
        Income::factory()->create(['occurred_on' => "{$thisYear}-01-10", 'amount_gbp_minor' => 50000]);
        Expense::factory()->create(['occurred_on' => "{$thisYear}-01-15", 'amount_gbp_minor' => 20000]);

        // Last year's rows must not leak into this year's headline numbers.
        Expense::factory()->create([
            'occurred_on' => now()->subYear()->format('Y-m-d'),
            'amount_gbp_minor' => 99900,
        ]);

        $this->actingAs($this->user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('£500.00')  // income this year
            ->assertSee('£200.00')  // expenses this year
            ->assertSee('£300.00')  // profit
            ->assertSee('All-time expenses')
            ->assertSee('£1,199.00'); // all-time total includes last year's expense
    }

    public function test_dashboard_excludes_drafts_from_totals(): void
    {
        $thisYear = now()->format('Y');
        Expense::factory()->create([
            'occurred_on' => "{$thisYear}-02-01",
            'amount_gbp_minor' => 12300,
            'is_draft' => true,
            'description' => 'Unconfirmed capture',
        ]);

        $this->actingAs($this->user)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('£123.00 this month')
            ->assertDontSee('Unconfirmed capture');
    }

    public function test_dashboard_shows_personal_card_reimbursement_total(): void
    {
        $personal = PaymentMethod::factory()->create(['is_personal' => true]);
        Expense::factory()->create([
            'occurred_on' => now()->format('Y-m-d'),
            'amount_gbp_minor' => 4500,
            'payment_method_id' => $personal->id,
        ]);

        $this->actingAs($this->user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('To reimburse')
            ->assertSee('£45.00');
    }

    public function test_dashboard_shows_recent_activity_and_top_categories(): void
    {
        $category = Category::factory()->create(['name' => 'Hosting']);
        Expense::factory()->create([
            'occurred_on' => now()->format('Y-m-d'),
            'amount_gbp_minor' => 3000,
            'category_id' => $category->id,
            'description' => 'OCI compute',
        ]);

        $this->actingAs($this->user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Recent activity')
            ->assertSee('OCI compute')
            ->assertSee('Hosting');
    }

    public function test_dashboard_shows_all_time_totals_and_profit(): void
    {
        Income::factory()->create([
            'occurred_on' => now()->subYears(2)->format('Y-m-d'),
            'amount_gbp_minor' => 100000,
        ]);
        Expense::factory()->create([
            'occurred_on' => now()->subYears(2)->format('Y-m-d'),
            'amount_gbp_minor' => 40000,
        ]);
        CashFlow::factory()->create([
            'occurred_on' => now()->subYears(2)->format('Y-m-d'),
            'amount_gbp_minor' => 65000,
        ]);

        $this->actingAs($this->user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('All-time income')
            ->assertSee('£1,000.00')
            ->assertSee('All-time expenses')
            ->assertSee('£400.00')
            ->assertSee('All-time profit')
            ->assertSee('£600.00')
            ->assertSee('All-time cash flows')
            ->assertSee('£650.00');
    }

    public function test_dashboard_shows_top_income_categories(): void
    {
        $category = Category::factory()->create(['name' => 'Consulting']);
        Income::factory()->create([
            'occurred_on' => now()->format('Y-m-d'),
            'amount_gbp_minor' => 50000,
            'category_id' => $category->id,
            'description' => 'Client project',
        ]);

        $this->actingAs($this->user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Top income categories this year')
            ->assertSee('Consulting');
    }
}
