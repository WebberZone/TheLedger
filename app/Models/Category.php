<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property TransactionType $type
 * @property string|null $report_group
 * @property string|null $account_code
 * @property string|null $description
 * @property bool $is_active
 * @property int $sort_order
 * @property Collection<int, Transaction> $transactions
 */
class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'report_group',
        'account_code',
        'description',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Active categories in display order — for select dropdowns.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    /**
     * Categories for one ledger (expense | income | cash_flow).
     */
    public function scopeForType($query, TransactionType $type)
    {
        return $query->where('type', $type->value);
    }
}
