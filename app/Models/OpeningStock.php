<?php

namespace App\Models;

use Database\Factories\OpeningStockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['number', 'stock_date', 'warehouse_id', 'status', 'notes', 'created_by', 'posted_by', 'posted_at'])]
class OpeningStock extends Model
{
    /** @use HasFactory<OpeningStockFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['stock_date' => 'date', 'posted_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OpeningStockItem::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}
