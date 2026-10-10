<?php

namespace App\Services;

use App\Models\CashAccount;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use App\StockMovementType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchasingService
{
    public function __construct(
        private InventoryService $inventoryService,
        private DocumentNumberService $numberService,
        private PaymentService $paymentService,
    ) {}

    /** @param array<int, string> $quantities keyed by purchase order item id */
    public function receive(PurchaseOrder $purchaseOrder, array $quantities, User $user): GoodsReceipt
    {
        return DB::transaction(function () use ($purchaseOrder, $quantities, $user): GoodsReceipt {
            $purchaseOrder = PurchaseOrder::query()->whereKey($purchaseOrder->getKey())->lockForUpdate()->firstOrFail();
            if (! in_array($purchaseOrder->status, ['approved', 'partially_received'], true)) {
                throw ValidationException::withMessages(['status' => 'PO harus disetujui sebelum barang diterima.']);
            }

            $purchaseOrder->load(['supplier', 'warehouse']);

            if ($purchaseOrder->supplier === null || $purchaseOrder->supplier->status !== 'active') {
                throw ValidationException::withMessages(['supplier_id' => 'Supplier tidak aktif atau tidak tersedia.']);
            }

            $receipt = GoodsReceipt::create([
                'number' => $this->numberService->next('GR'),
                'purchase_order_id' => $purchaseOrder->id,
                'receipt_date' => today(),
                'status' => 'posted',
                'total' => 0,
                'created_by' => $user->id,
                'posted_at' => now(),
            ]);
            $receiptTotal = '0.00';

            foreach ($quantities as $itemId => $quantity) {
                if (! is_numeric($quantity) || (float) $quantity <= 0) {
                    continue;
                }
                $item = PurchaseOrderItem::query()->where('purchase_order_id', $purchaseOrder->id)->whereKey($itemId)->lockForUpdate()->firstOrFail();
                $remaining = bcsub($item->ordered_quantity, $item->received_quantity, 3);
                if (bccomp($quantity, $remaining, 3) === 1) {
                    throw ValidationException::withMessages(['quantity' => "Penerimaan {$item->product_id} melebihi sisa PO."]);
                }
                $lineTotal = bcmul($quantity, $item->unit_price, 2);
                $receipt->items()->create(['purchase_order_item_id' => $item->id, 'product_id' => $item->product_id, 'quantity' => $quantity, 'unit_price' => $item->unit_price, 'line_total' => $lineTotal]);
                $item->increment('received_quantity', $quantity);
                $receiptTotal = bcadd($receiptTotal, $lineTotal, 2);
                $this->inventoryService->increase($item->product()->firstOrFail(), $purchaseOrder->warehouse, $quantity, StockMovementType::PurchaseReceipt, $user, $receipt, $receipt->number);
            }

            if (bccomp($receiptTotal, '0', 2) !== 1) {
                throw ValidationException::withMessages(['items' => 'Minimal satu jumlah penerimaan harus diisi.']);
            }

            $receipt->update(['total' => $receiptTotal]);
            $purchaseOrder->increment('outstanding_amount', $receiptTotal);

            if ($purchaseOrder->payment_type === 'cash') {
                $cashAccount = CashAccount::query()
                    ->whereKey($purchaseOrder->cash_account_id)
                    ->where('is_active', true)
                    ->first();

                if ($cashAccount === null) {
                    throw ValidationException::withMessages(['cash_account_id' => 'Akun kas atau bank untuk pembelian cash tidak tersedia.']);
                }

                $this->paymentService->paySupplier($purchaseOrder, $cashAccount, $receiptTotal, $user);
            }

            $hasRemaining = $purchaseOrder->items()->whereColumn('received_quantity', '<', 'ordered_quantity')->exists();
            $purchaseOrder->update(['status' => $hasRemaining ? 'partially_received' : 'completed']);

            return $receipt->fresh('items');
        });
    }
}
