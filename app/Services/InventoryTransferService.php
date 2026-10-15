<?php

namespace App\Services;

use App\Models\InventoryTransfer;
use App\Models\User;
use App\StockMovementType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryTransferService
{
    public function __construct(
        private InventoryService $inventory,
        private AccountingPeriodService $periods,
        private SubmissionTokenService $tokens,
    ) {}

    public function post(InventoryTransfer $transfer, User $user, ?string $submissionToken = null): InventoryTransfer
    {
        return DB::transaction(function () use ($transfer, $user, $submissionToken): InventoryTransfer {
            $transfer = InventoryTransfer::query()->whereKey($transfer->getKey())->lockForUpdate()->firstOrFail();
            $this->tokens->claim($submissionToken, $user, 'inventory-transfer.post', $transfer);

            if ($transfer->status !== 'draft') {
                throw ValidationException::withMessages(['transfer' => 'Transfer ini sudah diposting.']);
            }

            $this->periods->ensureOpen($transfer->transfer_date);
            $transfer->load(['items.product', 'sourceWarehouse', 'destinationWarehouse']);
            $stockKeys = $transfer->items->flatMap(fn ($item): array => [
                [
                    'product_id' => $item->product_id,
                    'warehouse_id' => $transfer->source_warehouse_id,
                ],
                [
                    'product_id' => $item->product_id,
                    'warehouse_id' => $transfer->destination_warehouse_id,
                ],
            ])->all();
            $this->inventory->lockBalances($stockKeys);

            foreach ($transfer->items as $item) {
                $this->inventory->decrease($item->product, $transfer->sourceWarehouse, $item->quantity, StockMovementType::TransferOut, $user, $transfer, $transfer->number, "Transfer ke {$transfer->destinationWarehouse->name}", $transfer->transfer_date);
                $this->inventory->increase($item->product, $transfer->destinationWarehouse, $item->quantity, StockMovementType::TransferIn, $user, $transfer, $transfer->number, "Transfer dari {$transfer->sourceWarehouse->name}", $transfer->transfer_date);
            }

            $transfer->update(['status' => 'posted', 'posted_by' => $user->id, 'posted_at' => now()]);

            return $transfer->fresh();
        }, attempts: 3);
    }
}
