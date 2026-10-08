<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['number', 'customer_id', 'warehouse_id', 'sale_date', 'due_date', 'status', 'total', 'paid_amount', 'outstanding_amount', 'notes', 'created_by', 'posted_at'])]
class Sale extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['sale_date' => 'date', 'due_date' => 'date', 'total' => 'decimal:2', 'paid_amount' => 'decimal:2', 'outstanding_amount' => 'decimal:2', 'posted_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
