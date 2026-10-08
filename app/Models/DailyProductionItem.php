<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['daily_production_id', 'product_id', 'quantity', 'rejected_quantity'])]
class DailyProductionItem extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'rejected_quantity' => 'decimal:3'];
    }

    public function production(): BelongsTo
    {
        return $this->belongsTo(DailyProduction::class, 'daily_production_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
