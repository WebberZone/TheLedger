<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $account
 * @property string $currency
 * @property Carbon $balance_on
 * @property int $balance_minor
 * @property string|null $notes
 * @property string $balance_major
 */
class AccountBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'account',
        'currency',
        'balance_on',
        'balance_minor',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'balance_on' => 'date',
            'balance_minor' => 'integer',
        ];
    }

    public function getBalanceMajorAttribute(): string
    {
        return number_format($this->balance_minor / 100, 2, '.', '');
    }
}
