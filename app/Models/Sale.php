<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['number', 'customer_id', 'warehouse_id', 'sale_date', 'payment_type', 'payment_method', 'sender_bank', 'due_date', 'cash_account_id', 'status', 'delivery_status', 'total', 'paid_amount', 'outstanding_amount', 'returned_amount', 'return_status', 'notes', 'created_by', 'posted_at', 'cancelled_by', 'cancelled_at', 'cancellation_reason'])]
class Sale extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['sale_date' => 'date', 'due_date' => 'date', 'total' => 'decimal:2', 'paid_amount' => 'decimal:2', 'outstanding_amount' => 'decimal:2', 'returned_amount' => 'decimal:2', 'posted_at' => 'datetime', 'cancelled_at' => 'datetime'];
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

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SalesReturn::class);
    }

    public function deliveryNotes(): HasMany
    {
        return $this->hasMany(DeliveryNote::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }
}
