<?php

namespace Tests\Feature;

use App\Models\BusinessUnit;
use App\Models\Category;
use App\Models\Expense;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LookupTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_index_requires_login(): void
    {
        $this->get('/lookups')->assertRedirect('/login');
    }

    public function test_index_shows_all_lookup_lists(): void
    {
        $category = Category::factory()->create(['name' => 'Hosting']);
        $method = PaymentMethod::factory()->create(['name' => 'Amex']);
        $unit = BusinessUnit::factory()->create(['name' => 'WebberZone']);

        $this->actingAs($this->user)
            ->get('/lookups')
            ->assertOk()
            ->assertSee('Categories')
            ->assertSee('Payment methods')
            ->assertSee('Business units')
            ->assertSee($category->name)
            ->assertSee($method->name)
            ->assertSee($unit->name);
    }

    public function test_can_create_a_category(): void
    {
        $this->actingAs($this->user)
            ->post('/lookups/categories', [
                'name' => 'Hosting',
                'type' => 'expense',
                'report_group' => 'Software',
                'account_code' => '7500',
                'description' => 'Servers and hosting',
                'is_active' => '1',
                'sort_order' => '10',
            ])
            ->assertRedirect('/lookups')
            ->assertSessionHas('status');

        $this->assertDatabaseHas('categories', [
            'name' => 'Hosting',
            'type' => 'expense',
            'report_group' => 'Software',
            'account_code' => '7500',
            'description' => 'Servers and hosting',
            'is_active' => true,
            'sort_order' => 10,
        ]);
    }

    public function test_can_create_a_payment_method(): void
    {
        $this->actingAs($this->user)
            ->post('/lookups/payment-methods', [
                'name' => 'Revolut Personal',
                'is_personal' => '1',
                'is_active' => '1',
            ])
            ->assertRedirect('/lookups')
            ->assertSessionHas('status');

        $this->assertDatabaseHas('payment_methods', [
            'name' => 'Revolut Personal',
            'is_personal' => true,
            'is_active' => true,
        ]);
    }

    public function test_can_create_a_business_unit(): void
    {
        $this->actingAs($this->user)
            ->post('/lookups/business-units', [
                'name' => 'Aqua-Air',
                'is_active' => '1',
            ])
            ->assertRedirect('/lookups')
            ->assertSessionHas('status');

        $this->assertDatabaseHas('business_units', [
            'name' => 'Aqua-Air',
            'is_active' => true,
        ]);
    }

    public function test_create_validates_name_and_type(): void
    {
        $this->actingAs($this->user)
            ->post('/lookups/categories', [
                'name' => '',
                'type' => 'invalid',
                'sort_order' => '-1',
            ])
            ->assertSessionHasErrors(['name', 'type', 'sort_order']);
    }

    public function test_can_edit_a_category(): void
    {
        $category = Category::factory()->create(['name' => 'Hosting']);

        $this->actingAs($this->user)
            ->get("/lookups/categories/{$category->id}/edit")
            ->assertOk()
            ->assertSee($category->name);
    }

    public function test_can_update_a_category(): void
    {
        $category = Category::factory()->create(['name' => 'Hosting']);

        $this->actingAs($this->user)
            ->patch("/lookups/categories/{$category->id}", [
                'name' => 'Cloud hosting',
                'type' => 'expense',
                'report_group' => 'Software',
                'account_code' => '7501',
                'description' => 'AWS, OCI, etc.',
                'is_active' => '1',
                'sort_order' => '20',
            ])
            ->assertRedirect('/lookups')
            ->assertSessionHas('status');

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Cloud hosting',
            'account_code' => '7501',
            'is_active' => true,
        ]);
    }

    public function test_can_update_a_payment_method(): void
    {
        $method = PaymentMethod::factory()->create(['name' => 'Amex', 'is_personal' => false]);

        $this->actingAs($this->user)
            ->patch("/lookups/payment-methods/{$method->id}", [
                'name' => 'Amex BA',
                'is_personal' => '1',
                'is_active' => '0',
            ])
            ->assertRedirect('/lookups');

        $this->assertDatabaseHas('payment_methods', [
            'id' => $method->id,
            'name' => 'Amex BA',
            'is_personal' => true,
            'is_active' => false,
        ]);
    }

    public function test_can_update_a_business_unit(): void
    {
        $unit = BusinessUnit::factory()->create(['name' => 'Beyond Develop']);

        $this->actingAs($this->user)
            ->patch("/lookups/business-units/{$unit->id}", [
                'name' => 'Beyond Develop Ltd',
                'is_active' => '0',
            ])
            ->assertRedirect('/lookups');

        $this->assertDatabaseHas('business_units', [
            'id' => $unit->id,
            'name' => 'Beyond Develop Ltd',
            'is_active' => false,
        ]);
    }

    public function test_can_delete_an_unused_category(): void
    {
        $category = Category::factory()->create();

        $this->actingAs($this->user)
            ->delete("/lookups/categories/{$category->id}")
            ->assertRedirect('/lookups')
            ->assertSessionHas('status');

        $this->assertModelMissing($category);
    }

    public function test_can_delete_an_unused_payment_method(): void
    {
        $method = PaymentMethod::factory()->create();

        $this->actingAs($this->user)
            ->delete("/lookups/payment-methods/{$method->id}")
            ->assertRedirect('/lookups')
            ->assertSessionHas('status');

        $this->assertModelMissing($method);
    }

    public function test_can_delete_an_unused_business_unit(): void
    {
        $unit = BusinessUnit::factory()->create();

        $this->actingAs($this->user)
            ->delete("/lookups/business-units/{$unit->id}")
            ->assertRedirect('/lookups')
            ->assertSessionHas('status');

        $this->assertModelMissing($unit);
    }

    public function test_delete_success_message_is_shown_once_and_green(): void
    {
        $method = PaymentMethod::factory()->create();

        $this->actingAs($this->user)
            ->delete("/lookups/payment-methods/{$method->id}");

        $content = $this->get('/lookups')->getContent();

        $this->assertSame(1, substr_count($content, 'Payment method deleted successfully.'));
        $this->assertStringContainsString('bg-green-50', $content);
        $this->assertStringNotContainsString('bg-red-50', $content);
    }

    public function test_delete_error_message_is_shown_once_and_red(): void
    {
        $category = Category::factory()->create();
        Expense::factory()->create(['category_id' => $category->id]);

        $this->actingAs($this->user)
            ->delete("/lookups/categories/{$category->id}");

        $content = $this->get('/lookups')->getContent();

        $this->assertSame(1, substr_count($content, 'Cannot delete this Category: it is used by existing transactions.'));
        $this->assertStringContainsString('bg-red-50', $content);
    }

    public function test_cannot_delete_a_category_used_by_transactions(): void
    {
        $category = Category::factory()->create();
        Expense::factory()->create(['category_id' => $category->id]);

        $this->actingAs($this->user)
            ->delete("/lookups/categories/{$category->id}")
            ->assertRedirect('/lookups')
            ->assertSessionHas('error');

        $this->assertModelExists($category);
    }

    public function test_unknown_lookup_type_returns_404(): void
    {
        $this->actingAs($this->user)
            ->get('/lookups/unknown/create')
            ->assertNotFound();
    }
}
