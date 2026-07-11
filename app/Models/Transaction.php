<?php

namespace App\Models;

use App\Enums\TransactionType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Base for the three ledgers (expenses, income, cash flows). They share
 * one table and one shape; Expense/Income/CashFlow are thin subclasses
 * that scope to their own `type` — single-table inheritance.
 *
 * @property int $id
 * @property TransactionType $type
 * @property Carbon $occurred_on
 * @property string $counterparty
 * @property string|null $description
 * @property int $amount_minor
 * @property int $vat_minor
 * @property string $currency
 * @property float|null $fx_rate
 * @property int $amount_gbp_minor
 * @property int|null $category_id
 * @property int|null $business_unit_id
 * @property int|null $payment_method_id
 * @property bool $is_billable
 * @property string|null $billed_ref
 * @property bool $is_draft
 * @property string|null $notes
 * @property array<string, mixed>|null $ocr_json
 * @property int $total_minor
 * @property string $total_major
 * @property string $amount_major
 * @property string $vat_major
 * @property string $amount_gbp_major
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Category|null $category
 * @property BusinessUnit|null $businessUnit
 * @property PaymentMethod|null $paymentMethod
 * @property Collection<int, Receipt> $receipts
 */
class Transaction extends Model
{
    use HasFactory;

    protected $table = 'transactions';

    protected $fillable = [
        'type',
        'occurred_on',
        'counterparty',
        'description',
        'amount_minor',
        'vat_minor',
        'currency',
        'fx_rate',
        'amount_gbp_minor',
        'category_id',
        'business_unit_id',
        'payment_method_id',
        'is_billable',
        'billed_ref',
        'is_draft',
        'notes',
        'ocr_json',
    ];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'occurred_on' => 'date',
            'amount_minor' => 'integer',
            'vat_minor' => 'integer',
            'fx_rate' => 'decimal:6',
            'amount_gbp_minor' => 'integer',
            'is_billable' => 'boolean',
            'is_draft' => 'boolean',
            'ocr_json' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<BusinessUnit, $this>
     */
    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    /**
     * @return BelongsTo<PaymentMethod, $this>
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /**
     * @return HasMany<Receipt, $this>
     */
    public function receipts(): HasMany
    {
        // Explicit FK: subclasses would otherwise guess 'expense_id' etc.
        // from their own class name.
        return $this->hasMany(Receipt::class, 'transaction_id');
    }

    /**
     * Gross in the original currency: net + VAT, in minor units.
     */
    public function getTotalMinorAttribute(): int
    {
        return $this->amount_minor + $this->vat_minor;
    }

    /**
     * Formatted-for-display helpers. Money maths stays in integer pence;
     * formatting happens only at the view layer.
     */
    public function getAmountMajorAttribute(): string
    {
        return number_format($this->amount_minor / 100, 2, '.', '');
    }

    public function getVatMajorAttribute(): string
    {
        return number_format($this->vat_minor / 100, 2, '.', '');
    }

    public function getTotalMajorAttribute(): string
    {
        return number_format($this->total_minor / 100, 2, '.', '');
    }

    public function getAmountGbpMajorAttribute(): string
    {
        return number_format($this->amount_gbp_minor / 100, 2, '.', '');
    }

    /**
     * The human-facing name of the transaction: the description when it
     * exists, otherwise the counterparty (supplier/customer/source). This
     * keeps notifications and headings from looking like every transaction
     * with the same customer is a duplicate.
     */
    public function displayName(): string
    {
        return $this->description ?? $this->counterparty;
    }

    /**
     * Filter to an occurred_on date range; either bound may be null.
     */
    public function scopeOccurredBetween(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from, fn (Builder $q) => $q->whereDate('occurred_on', '>=', $from))
            ->when($to, fn (Builder $q) => $q->whereDate('occurred_on', '<=', $to));
    }
}
