<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['number', 'purchase_order_id', 'receipt_date', 'status', 'total', 'notes', 'proof_path', 'created_by', 'posted_at'])]
class GoodsReceipt extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['receipt_date' => 'date', 'total' => 'decimal:2', 'posted_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
