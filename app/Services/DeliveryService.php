<?php

namespace App\Services;

use App\Models\DeliveryNote;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\StockMovementType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryService
{
    public function __construct(
        private InventoryService $inventoryService,
        private DocumentNumberService $numberService,
        private AccountingPeriodService $periods,
        private SubmissionTokenService $tokens,
    ) {}

    /** @param array<int, string|null> $quantities keyed by sale item id */
    public function post(Sale $sale, array $quantities, string $deliveryDate, ?string $notes, User $user, ?string $submissionToken = null): DeliveryNote
    {
        return DB::transaction(function () use ($sale, $quantities, $deliveryDate, $notes, $user, $submissionToken): DeliveryNote {
            $sale = Sale::query()->whereKey($sale->getKey())->lockForUpdate()->firstOrFail();
            $this->tokens->claim($submissionToken, $user, 'delivery.post', $sale);
            $this->periods->ensureOpen($deliveryDate);
            if (in_array($sale->status, ['draft', 'cancelled'], true)) {
                throw ValidationException::withMessages(['status' => 'Penjualan harus diposting sebelum barang dapat dikirim.']);
            }

            if ($sale->delivery_status === 'complete') {
                throw ValidationException::withMessages(['status' => 'Seluruh barang pada penjualan ini sudah dikirim.']);
            }

            $sale->load('warehouse');
            $deliveryNote = DeliveryNote::create([
                'number' => $this->numberService->next('SJ'),
                'sale_id' => $sale->id,
                'warehouse_id' => $sale->warehouse_id,
                'delivery_date' => $deliveryDate,
                'status' => 'posted',
                'notes' => $notes,
                'created_by' => $user->id,
                'posted_by' => $user->id,
                'posted_at' => now(),
            ]);

            foreach ($quantities as $saleItemId => $quantity) {
                if (! is_numeric($quantity) || bccomp((string) $quantity, '0', 3) !== 1) {
                    continue;
                }

                $saleItem = SaleItem::query()
                    ->where('sale_id', $sale->id)
                    ->whereKey($saleItemId)
                    ->lockForUpdate()
                    ->firstOrFail();
                $alreadyDelivered = (string) $saleItem->deliveryNoteItems()->sum('quantity');
                $remaining = bcsub($saleItem->quantity, $alreadyDelivered, 3);

                if (bccomp((string) $quantity, $remaining, 3) === 1) {
                    throw ValidationException::withMessages(['quantities' => 'Jumlah pengiriman melebihi sisa barang yang belum dikirim.']);
                }

                $deliveryNote->items()->create([
                    'sale_item_id' => $saleItem->id,
                    'product_id' => $saleItem->product_id,
                    'quantity' => $quantity,
                ]);
                $this->inventoryService->decrease(
                    $saleItem->product()->firstOrFail(),
                    $sale->warehouse,
                    (string) $quantity,
                    StockMovementType::Sale,
                    $user,
                    $deliveryNote,
                    $deliveryNote->number,
                    "Pengiriman penjualan {$sale->number}",
                );
            }

            if (! $deliveryNote->items()->exists()) {
                throw ValidationException::withMessages(['quantities' => 'Minimal satu jumlah pengiriman harus diisi.']);
            }

            $allDelivered = $sale->items()
                ->withSum('deliveryNoteItems', 'quantity')
                ->get()
                ->every(fn (SaleItem $item): bool => bccomp($item->quantity, (string) ($item->delivery_note_items_sum_quantity ?? 0), 3) === 0);
            $sale->update(['delivery_status' => $allDelivered ? 'complete' : 'partial']);

            return $deliveryNote->fresh(['items.product.unit', 'sale.customer', 'warehouse']);
        });
    }
}
