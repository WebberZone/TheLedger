<?php

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\BusinessUnit;
use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /** @var class-string<Transaction> */
    protected $model = Transaction::class;

    public function definition(): array
    {
        $amountMinor = fake()->numberBetween(100, 50000);

        return [
            'type' => TransactionType::Expense,
            'occurred_on' => fake()->dateTimeBetween('-1 year')->format('Y-m-d'),
            'counterparty' => fake()->company(),
            'description' => fake()->sentence(4),
            'amount_minor' => $amountMinor,
            'vat_minor' => 0,
            'currency' => 'GBP',
            'fx_rate' => 1,
            'amount_gbp_minor' => $amountMinor,
            'category_id' => Category::factory(),
            'business_unit_id' => BusinessUnit::factory(),
            'payment_method_id' => PaymentMethod::factory(),
            'is_billable' => false,
        ];
    }

    /**
     * A USD transaction with a realistic rate, GBP amount derived the
     * same way the app does it (gross = net + VAT).
     */
    public function usd(float $rate = 0.79): static
    {
        return $this->state(
            function (array $attributes) use ($rate) {
                $gross = $attributes['amount_minor'] + $attributes['vat_minor'];

                return [
                    'currency' => 'USD',
                    'fx_rate' => $rate,
                    'amount_gbp_minor' => (int) round($gross * $rate),
                ];
            }
        );
    }
}
