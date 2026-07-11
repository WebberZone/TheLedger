<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $transaction_id
 * @property string $path
 * @property string|null $original_filename
 * @property string|null $content_type
 * @property int $byte_size
 * @property array<string, mixed>|null $ocr_json
 * @property Transaction $transaction
 */
class Receipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'path',
        'original_filename',
        'content_type',
        'byte_size',
        'ocr_json',
    ];

    protected function casts(): array
    {
        return [
            'byte_size' => 'integer',
            'ocr_json' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
