<?php

namespace App\Models;

use App\ProductionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['number', 'production_date', 'warehouse_id', 'production_type', 'status', 'notes', 'created_by', 'posted_by', 'posted_at'])]
class DailyProduction extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'production_date' => 'date',
            'production_type' => ProductionType::class,
            'posted_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(DailyProductionItem::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(DailyProductionMaterial::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
