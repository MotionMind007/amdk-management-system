<?php

namespace App\Services;

use App\Models\DailyProduction;
use App\Models\ProductComposition;
use App\Models\User;
use App\StockMovementType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductionService
{
    public function __construct(private InventoryService $inventoryService) {}

    public function post(DailyProduction $production, User $user): DailyProduction
    {
        return DB::transaction(function () use ($production, $user): DailyProduction {
            $production = DailyProduction::query()->whereKey($production->getKey())->lockForUpdate()->firstOrFail();
            if ($production->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Hanya rekap draft yang dapat diposting.']);
            }

            $production->load('items.product');
            if ($production->items->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Minimal satu hasil produksi harus diisi.']);
            }

            foreach ($production->items as $item) {
                $compositions = ProductComposition::query()
                    ->where('product_id', $item->product_id)
                    ->with('component')
                    ->get();
                if ($compositions->isEmpty()) {
                    throw ValidationException::withMessages(['items' => "Komposisi {$item->product->name} belum diatur."]);
                }

                foreach ($compositions as $composition) {
                    $consumedQuantity = bcmul($item->quantity, $composition->quantity, 3);
                    $this->inventoryService->decrease($composition->component, $production->warehouse, $consumedQuantity, StockMovementType::ProductionConsumption, $user, $production, $production->number);
                }

                $this->inventoryService->increase($item->product, $production->warehouse, $item->quantity, StockMovementType::ProductionOutput, $user, $production, $production->number);
            }

            $production->update(['status' => 'posted', 'posted_by' => $user->id, 'posted_at' => now()]);

            return $production->fresh('items');
        });
    }
}
