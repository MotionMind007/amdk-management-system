<?php

namespace App\Models;

use Database\Factories\DeliveryNoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['number', 'sale_id', 'warehouse_id', 'delivery_date', 'status', 'notes', 'created_by', 'posted_by', 'posted_at'])]
class DeliveryNote extends Model
{
    /** @use HasFactory<DeliveryNoteFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['delivery_date' => 'date', 'posted_at' => 'datetime'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryNoteItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
