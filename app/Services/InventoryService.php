<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\StockMovementType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function increase(
        Product $product,
        Warehouse $warehouse,
        string $quantity,
        StockMovementType $type,
        ?User $user = null,
        ?Model $source = null,
        ?string $referenceNumber = null,
        ?string $notes = null,
        ?CarbonInterface $occurredAt = null,
    ): StockMovement {
        return $this->move($product, $warehouse, $quantity, 'in', $type, $user, $source, $referenceNumber, $notes, $occurredAt);
    }

    public function decrease(
        Product $product,
        Warehouse $warehouse,
        string $quantity,
        StockMovementType $type,
        ?User $user = null,
        ?Model $source = null,
        ?string $referenceNumber = null,
        ?string $notes = null,
        ?CarbonInterface $occurredAt = null,
    ): StockMovement {
        return $this->move($product, $warehouse, $quantity, 'out', $type, $user, $source, $referenceNumber, $notes, $occurredAt);
    }

    private function move(
        Product $product,
        Warehouse $warehouse,
        string $quantity,
        string $direction,
        StockMovementType $type,
        ?User $user,
        ?Model $source,
        ?string $referenceNumber,
        ?string $notes,
        ?CarbonInterface $occurredAt,
    ): StockMovement {
        $isProductActive = $product->exists && Product::query()
            ->whereKey($product->getKey())
            ->where('is_active', true)
            ->exists();

        if (! $isProductActive) {
            throw ValidationException::withMessages(['product_id' => 'Produk atau bahan tidak aktif atau tidak tersedia.']);
        }

        $isWarehouseActive = $warehouse->exists && Warehouse::query()
            ->whereKey($warehouse->getKey())
            ->where('is_active', true)
            ->exists();

        if (! $isWarehouseActive) {
            throw ValidationException::withMessages(['warehouse_id' => 'Gudang tidak aktif atau tidak tersedia.']);
        }

        if (! is_numeric($quantity) || (float) $quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'Jumlah stok harus lebih besar dari nol.']);
        }

        return DB::transaction(function () use ($product, $warehouse, $quantity, $direction, $type, $user, $source, $referenceNumber, $notes, $occurredAt): StockMovement {
            StockBalance::query()->firstOrCreate([
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
            ], ['quantity' => 0]);

            $balance = StockBalance::query()
                ->whereBelongsTo($product)
                ->whereBelongsTo($warehouse)
                ->lockForUpdate()
                ->firstOrFail();
            $balanceBefore = $balance->quantity;

            if ($direction === 'out') {
                $updated = StockBalance::query()
                    ->whereKey($balance->id)
                    ->where('quantity', '>=', $quantity)
                    ->decrement('quantity', $quantity);

                if ($updated === 0) {
                    throw ValidationException::withMessages(['quantity' => 'Stok tidak mencukupi untuk transaksi ini.']);
                }
            } else {
                StockBalance::query()->whereKey($balance->id)->increment('quantity', $quantity);
            }

            $balanceAfter = $balance->fresh()->quantity;

            return StockMovement::create([
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'user_id' => $user?->id,
                'type' => $type,
                'direction' => $direction,
                'quantity' => $quantity,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'reference_number' => $referenceNumber,
                'notes' => $notes,
                'occurred_at' => $occurredAt ?? now(),
            ]);
        });
    }
}
