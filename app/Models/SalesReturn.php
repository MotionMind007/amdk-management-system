<?php

namespace App\Models;

use Database\Factories\SalesReturnFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['number', 'sale_id', 'return_date', 'status', 'total', 'credit_amount', 'refund_amount', 'refund_account_id', 'reason', 'created_by', 'posted_at'])]
class SalesReturn extends Model
{
    /** @use HasFactory<SalesReturnFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'return_date' => 'date',
            'total' => 'decimal:2',
            'credit_amount' => 'decimal:2',
            'refund_amount' => 'decimal:2',
            'posted_at' => 'datetime',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesReturnItem::class);
    }

    public function refundAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'refund_account_id');
    }
}
