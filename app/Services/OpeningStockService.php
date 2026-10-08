<?php

namespace App\Services;

use App\Models\OpeningStock;
use App\Models\StockMovement;
use App\Models\User;
use App\StockMovementType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpeningStockService
{
    public function __construct(private InventoryService $inventoryService) {}

    public function post(OpeningStock $openingStock, User $user): OpeningStock
    {
        return DB::transaction(function () use ($openingStock, $user): OpeningStock {
            $openingStock = OpeningStock::query()
                ->whereKey($openingStock->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($openingStock->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Stok awal ini sudah diposting dan tidak dapat diproses kembali.']);
            }

            $openingStock->load(['items.product', 'warehouse']);

            foreach ($openingStock->items as $item) {
                $hasPreviousMovement = StockMovement::query()
                    ->whereBelongsTo($item->product)
                    ->whereBelongsTo($openingStock->warehouse)
                    ->exists();

                if ($hasPreviousMovement) {
                    throw ValidationException::withMessages([
                        'stock' => "Stok awal {$item->product->name} tidak dapat diposting karena sudah memiliki pergerakan stok di gudang ini.",
                    ]);
                }

                $this->inventoryService->increase(
                    $item->product,
                    $openingStock->warehouse,
                    $item->quantity,
                    StockMovementType::OpeningBalance,
                    $user,
                    $openingStock,
                    $openingStock->number,
                    $openingStock->notes,
                    $openingStock->stock_date,
                );
            }

            $openingStock->update([
                'status' => 'posted',
                'posted_by' => $user->id,
                'posted_at' => now(),
            ]);

            return $openingStock->fresh(['items.product', 'warehouse']);
        });
    }
}
