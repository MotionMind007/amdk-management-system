<?php

namespace App\Services;

use App\Models\StockOpname;
use App\Models\User;
use App\StockMovementType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockOpnameService
{
    public function __construct(private InventoryService $inventoryService) {}

    public function post(StockOpname $stockOpname, User $user): StockOpname
    {
        return DB::transaction(function () use ($stockOpname, $user): StockOpname {
            $stockOpname = StockOpname::query()
                ->whereKey($stockOpname->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($stockOpname->status !== 'draft') {
                throw ValidationException::withMessages([
                    'status' => 'Stok opname ini sudah diposting dan tidak dapat diproses kembali.',
                ]);
            }

            $stockOpname->load(['items.product.unit', 'warehouse']);

            foreach ($stockOpname->items as $item) {
                $difference = $item->difference();

                if ($difference === 0.0) {
                    continue;
                }

                $quantity = number_format(abs($difference), 3, '.', '');
                $movementNotes = "Stok opname periode {$stockOpname->period}: stok sistem {$item->system_quantity}, stok fisik {$item->physical_quantity}.";

                if (filled($item->notes)) {
                    $movementNotes .= " Keterangan: {$item->notes}";
                }

                if ($difference > 0) {
                    $this->inventoryService->increase(
                        $item->product,
                        $stockOpname->warehouse,
                        $quantity,
                        StockMovementType::StockOpname,
                        $user,
                        $stockOpname,
                        $stockOpname->number,
                        $movementNotes,
                    );
                } else {
                    $this->inventoryService->decrease(
                        $item->product,
                        $stockOpname->warehouse,
                        $quantity,
                        StockMovementType::StockOpname,
                        $user,
                        $stockOpname,
                        $stockOpname->number,
                        $movementNotes,
                    );
                }
            }

            $stockOpname->update([
                'status' => 'posted',
                'posted_by' => $user->id,
                'posted_at' => now(),
            ]);

            return $stockOpname->fresh(['items.product.unit', 'warehouse', 'creator', 'poster']);
        });
    }
}
