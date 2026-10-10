<?php

namespace App\Models;

use Database\Factories\DailyProductionMaterialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['daily_production_id', 'product_id', 'quantity'])]
class DailyProductionMaterial extends Model
{
    /** @use HasFactory<DailyProductionMaterialFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
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
