<?php

namespace App\Services;

use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\StockMovementType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseReturnService
{
    public function __construct(
        private InventoryService $inventoryService,
        private DocumentNumberService $numberService,
        private AccountingPeriodService $periods,
        private SubmissionTokenService $tokens,
    ) {}

    /** @param array<int, string|null> $quantities keyed by goods receipt item id */
    public function post(
        GoodsReceipt $goodsReceipt,
        array $quantities,
        string $returnDate,
        string $reason,
        ?int $refundAccountId,
        User $user,
        ?string $submissionToken = null,
    ): PurchaseReturn {
        return DB::transaction(function () use ($goodsReceipt, $quantities, $returnDate, $reason, $refundAccountId, $user, $submissionToken): PurchaseReturn {
            $this->periods->ensureOpen($returnDate);
            $goodsReceipt = GoodsReceipt::query()->whereKey($goodsReceipt->getKey())->lockForUpdate()->firstOrFail();
            $this->tokens->claim($submissionToken, $user, 'purchase-return.post', $goodsReceipt);
            if ($goodsReceipt->status !== 'posted') {
                throw ValidationException::withMessages(['status' => 'Hanya penerimaan yang sudah diposting yang dapat diretur.']);
            }

            $purchaseOrder = PurchaseOrder::query()->whereKey($goodsReceipt->purchase_order_id)->lockForUpdate()->firstOrFail();
            if ($purchaseOrder->status === 'cancelled') {
                throw ValidationException::withMessages(['status' => 'PO yang dibatalkan tidak dapat diretur.']);
            }

            $purchaseOrder->load('warehouse');
            $purchaseReturn = PurchaseReturn::create([
                'number' => $this->numberService->next('PR'),
                'purchase_order_id' => $purchaseOrder->id,
                'return_date' => $returnDate,
                'status' => 'posted',
                'reason' => $reason,
                'created_by' => $user->id,
                'posted_at' => now(),
            ]);
            $total = '0.00';

            foreach ($quantities as $receiptItemId => $quantity) {
                if (! is_numeric($quantity) || bccomp((string) $quantity, '0', 3) !== 1) {
                    continue;
                }

                $receiptItem = GoodsReceiptItem::query()
                    ->where('goods_receipt_id', $goodsReceipt->id)
                    ->whereKey($receiptItemId)
                    ->lockForUpdate()
                    ->firstOrFail();
                $alreadyReturned = (string) $receiptItem->returnItems()->sum('quantity');
                $remaining = bcsub($receiptItem->quantity, $alreadyReturned, 3);

                if (bccomp((string) $quantity, $remaining, 3) === 1) {
                    throw ValidationException::withMessages(['quantities' => 'Jumlah retur pembelian melebihi barang yang masih dapat diretur.']);
                }

                $lineTotal = bcmul((string) $quantity, $receiptItem->unit_price, 2);
                $purchaseReturn->items()->create([
                    'goods_receipt_item_id' => $receiptItem->id,
                    'purchase_order_item_id' => $receiptItem->purchase_order_item_id,
                    'product_id' => $receiptItem->product_id,
                    'quantity' => $quantity,
                    'unit_price' => $receiptItem->unit_price,
                    'line_total' => $lineTotal,
                ]);
                $receiptItem->purchaseOrderItem()->lockForUpdate()->firstOrFail()->decrement('received_quantity', $quantity);
                $this->inventoryService->decrease(
                    $receiptItem->product()->firstOrFail(),
                    $purchaseOrder->warehouse,
                    (string) $quantity,
                    StockMovementType::Reversal,
                    $user,
                    $purchaseReturn,
                    $purchaseReturn->number,
                    "Retur pembelian {$goodsReceipt->number}: {$reason}",
                );
                $total = bcadd($total, $lineTotal, 2);
            }

            if (bccomp($total, '0', 2) !== 1) {
                throw ValidationException::withMessages(['quantities' => 'Minimal satu jumlah retur harus diisi.']);
            }

            $supplierInvoice = SupplierInvoice::query()->where('goods_receipt_id', $goodsReceipt->id)->lockForUpdate()->first();
            $creditAmount = $supplierInvoice === null
                ? '0.00'
                : (bccomp($total, $supplierInvoice->outstanding_amount, 2) === 1 ? $supplierInvoice->outstanding_amount : $total);
            $refundAmount = $supplierInvoice === null ? '0.00' : bcsub($total, $creditAmount, 2);

            if ($supplierInvoice !== null && bccomp($refundAmount, $supplierInvoice->paid_amount, 2) === 1) {
                throw ValidationException::withMessages(['quantities' => 'Nilai retur melebihi nilai barang bersih yang telah diterima.']);
            }

            if (bccomp($refundAmount, '0', 2) === 1) {
                $this->recordSupplierRefund($purchaseReturn, $refundAccountId, $refundAmount, $user);
                $supplierInvoice?->decrement('paid_amount', $refundAmount);
                $purchaseOrder->decrement('paid_amount', $refundAmount);
            }

            if (bccomp($creditAmount, '0', 2) === 1) {
                $supplierInvoice?->decrement('outstanding_amount', $creditAmount);
                $purchaseOrder->decrement('outstanding_amount', $creditAmount);
            }

            if ($supplierInvoice !== null) {
                $supplierInvoice->refresh();
                $supplierInvoice->update([
                    'status' => bccomp($supplierInvoice->outstanding_amount, '0', 2) === 0
                        ? 'paid'
                        : (bccomp($supplierInvoice->paid_amount, '0', 2) === 1 ? 'partially_paid' : 'unpaid'),
                ]);
            }

            $purchaseOrder->increment('returned_amount', $total);
            $hasReceivedItems = $purchaseOrder->items()->where('received_quantity', '>', 0)->exists();
            $hasRemaining = $purchaseOrder->items()->whereColumn('received_quantity', '<', 'ordered_quantity')->exists();
            $purchaseOrder->update([
                'status' => ! $hasReceivedItems ? 'approved' : ($hasRemaining ? 'partially_received' : 'completed'),
                'return_status' => ! $hasReceivedItems ? 'complete' : 'partial',
            ]);
            $purchaseReturn->update([
                'total' => $total,
                'credit_amount' => $creditAmount,
                'refund_amount' => $refundAmount,
                'refund_account_id' => bccomp($refundAmount, '0', 2) === 1 ? $refundAccountId : null,
            ]);

            return $purchaseReturn->fresh(['items.product', 'purchaseOrder']);
        });
    }

    private function recordSupplierRefund(PurchaseReturn $purchaseReturn, ?int $refundAccountId, string $amount, User $user): void
    {
        $account = $refundAccountId === null
            ? null
            : CashAccount::query()->whereKey($refundAccountId)->lockForUpdate()->first();

        if ($account === null || ! $account->is_active) {
            throw ValidationException::withMessages(['refund_account_id' => 'Pilih akun kas atau bank untuk menerima refund supplier.']);
        }

        $account->increment('balance', $amount);
        CashTransaction::create([
            'cash_account_id' => $account->id,
            'transaction_date' => $purchaseReturn->return_date,
            'direction' => 'in',
            'amount' => $amount,
            'source_type' => $purchaseReturn->getMorphClass(),
            'source_id' => $purchaseReturn->id,
            'reference_number' => $purchaseReturn->number,
            'description' => "Refund supplier {$purchaseReturn->number}",
            'user_id' => $user->id,
        ]);
    }
}
