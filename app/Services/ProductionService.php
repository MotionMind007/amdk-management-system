<?php

namespace App\Services;

use App\Models\DailyProduction;
use App\Models\ProductComposition;
use App\Models\User;
use App\ProductionType;
use App\StockMovementType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductionService
{
    public function __construct(private InventoryService $inventoryService, private AccountingPeriodService $periods) {}

    public function post(DailyProduction $production, User $user): DailyProduction
    {
        return DB::transaction(function () use ($production, $user): DailyProduction {
            $production = DailyProduction::query()->whereKey($production->getKey())->lockForUpdate()->firstOrFail();
            $this->periods->ensureOpen($production->production_date);
            if ($production->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Hanya rekap draft yang dapat diposting.']);
            }

            $production->load(['items.product', 'materials.product', 'warehouse']);
            if ($production->items->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Minimal satu hasil produksi harus diisi.']);
            }

            $stockProductIds = $production->items->pluck('product_id')
                ->merge($production->materials->pluck('product_id'));

            if ($production->production_type === ProductionType::FinishedGood) {
                $stockProductIds = $stockProductIds->merge(
                    ProductComposition::query()
                        ->whereIn('product_id', $production->items->pluck('product_id'))
                        ->pluck('component_product_id'),
                );
            }

            $this->inventoryService->lockBalances(
                $stockProductIds
                    ->unique()
                    ->map(fn (int $productId): array => [
                        'product_id' => $productId,
                        'warehouse_id' => $production->warehouse_id,
                    ])
                    ->values()
                    ->all(),
            );

            if ($production->production_type === ProductionType::Packaging) {
                $this->postPackagingProduction($production, $user);
            } else {
                $this->postFinishedGoodProduction($production, $user);
            }

            $production->update(['status' => 'posted', 'posted_by' => $user->id, 'posted_at' => now()]);

            return $production->fresh(['items', 'materials']);
        }, attempts: 3);
    }

    private function postFinishedGoodProduction(DailyProduction $production, User $user): void
    {
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
    }

    private function postPackagingProduction(DailyProduction $production, User $user): void
    {
        if ($production->materials->isEmpty()) {
            throw ValidationException::withMessages(['materials' => 'Minimal satu bahan aktual harus diisi untuk produksi kemasan.']);
        }

        if ($production->items->pluck('product_id')->intersect($production->materials->pluck('product_id'))->isNotEmpty()) {
            throw ValidationException::withMessages(['materials' => 'Produk hasil tidak boleh digunakan sebagai bahan pada rekap yang sama.']);
        }

        foreach ($production->materials as $material) {
            $this->inventoryService->decrease($material->product, $production->warehouse, $material->quantity, StockMovementType::ProductionConsumption, $user, $production, $production->number);
        }

        foreach ($production->items as $item) {
            $this->inventoryService->increase($item->product, $production->warehouse, $item->quantity, StockMovementType::ProductionOutput, $user, $production, $production->number);
        }
    }
}
