<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property bool $is_personal
 * @property bool $is_active
 * @property Collection<int, Transaction> $transactions
 */
class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'is_personal',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_personal' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('name');
    }
}
