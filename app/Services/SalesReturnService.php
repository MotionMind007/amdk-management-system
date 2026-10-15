<?php

namespace App\Services;

use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalesReturn;
use App\Models\User;
use App\StockMovementType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesReturnService
{
    public function __construct(
        private InventoryService $inventoryService,
        private DocumentNumberService $numberService,
        private AccountingPeriodService $periods,
        private SubmissionTokenService $tokens,
    ) {}

    /** @param array<int, string|null> $quantities keyed by sale item id */
    public function post(
        Sale $sale,
        array $quantities,
        string $returnDate,
        string $reason,
        ?int $refundAccountId,
        User $user,
        ?string $submissionToken = null,
    ): SalesReturn {
        return DB::transaction(function () use ($sale, $quantities, $returnDate, $reason, $refundAccountId, $user, $submissionToken): SalesReturn {
            $this->periods->ensureOpen($returnDate);
            $sale = Sale::query()->whereKey($sale->getKey())->lockForUpdate()->firstOrFail();
            $this->tokens->claim($submissionToken, $user, 'sales-return.post', $sale);
            if (in_array($sale->status, ['draft', 'cancelled'], true)) {
                throw ValidationException::withMessages(['status' => 'Hanya penjualan yang sudah diposting yang dapat diretur.']);
            }

            $sale->load('warehouse');
            $productIds = SaleItem::query()
                ->where('sale_id', $sale->id)
                ->whereIn('id', array_keys($quantities))
                ->pluck('product_id');
            $this->inventoryService->lockBalances(
                $productIds->map(fn (int $productId): array => [
                    'product_id' => $productId,
                    'warehouse_id' => $sale->warehouse_id,
                ])->all(),
            );
            $salesReturn = SalesReturn::create([
                'number' => $this->numberService->next('SR'),
                'sale_id' => $sale->id,
                'return_date' => $returnDate,
                'status' => 'posted',
                'reason' => $reason,
                'created_by' => $user->id,
                'posted_at' => now(),
            ]);
            $total = '0.00';

            foreach ($quantities as $saleItemId => $quantity) {
                if (! is_numeric($quantity) || bccomp((string) $quantity, '0', 3) !== 1) {
                    continue;
                }

                $saleItem = SaleItem::query()
                    ->where('sale_id', $sale->id)
                    ->whereKey($saleItemId)
                    ->lockForUpdate()
                    ->firstOrFail();
                $alreadyReturned = (string) $saleItem->returnItems()->sum('quantity');
                $delivered = (string) $saleItem->deliveryNoteItems()->sum('quantity');
                $remaining = bcsub($delivered, $alreadyReturned, 3);

                if (bccomp((string) $quantity, $remaining, 3) === 1) {
                    throw ValidationException::withMessages(['quantities' => 'Jumlah retur penjualan melebihi produk yang masih dapat diretur.']);
                }

                $lineTotal = bcmul((string) $quantity, $saleItem->unit_price, 2);
                $salesReturn->items()->create([
                    'sale_item_id' => $saleItem->id,
                    'product_id' => $saleItem->product_id,
                    'quantity' => $quantity,
                    'unit_price' => $saleItem->unit_price,
                    'line_total' => $lineTotal,
                ]);
                $this->inventoryService->increase(
                    $saleItem->product()->firstOrFail(),
                    $sale->warehouse,
                    (string) $quantity,
                    StockMovementType::Reversal,
                    $user,
                    $salesReturn,
                    $salesReturn->number,
                    "Retur penjualan {$sale->number}: {$reason}",
                );
                $total = bcadd($total, $lineTotal, 2);
            }

            if (bccomp($total, '0', 2) !== 1) {
                throw ValidationException::withMessages(['quantities' => 'Minimal satu jumlah retur harus diisi.']);
            }

            $creditAmount = bccomp($total, $sale->outstanding_amount, 2) === 1
                ? $sale->outstanding_amount
                : $total;
            $refundAmount = bcsub($total, $creditAmount, 2);

            if (bccomp($refundAmount, $sale->paid_amount, 2) === 1) {
                throw ValidationException::withMessages(['quantities' => 'Nilai retur melebihi nilai bersih transaksi penjualan.']);
            }

            if (bccomp($refundAmount, '0', 2) === 1) {
                $this->recordCustomerRefund($salesReturn, $refundAccountId, $refundAmount, $user);
                $sale->decrement('paid_amount', $refundAmount);
            }

            if (bccomp($creditAmount, '0', 2) === 1) {
                $sale->decrement('outstanding_amount', $creditAmount);
            }

            $sale->increment('returned_amount', $total);
            $allReturned = $sale->items()
                ->withSum('returnItems', 'quantity')
                ->withSum('deliveryNoteItems', 'quantity')
                ->get()
                ->every(fn (SaleItem $item): bool => bccomp((string) ($item->delivery_note_items_sum_quantity ?? 0), (string) ($item->return_items_sum_quantity ?? 0), 3) === 0);
            $sale->refresh();
            $sale->update([
                'status' => bccomp($sale->outstanding_amount, '0', 2) === 0
                    ? 'paid'
                    : (bccomp($sale->paid_amount, '0', 2) === 1 ? 'partially_paid' : 'unpaid'),
                'return_status' => $allReturned ? 'complete' : 'partial',
            ]);
            $salesReturn->update([
                'total' => $total,
                'credit_amount' => $creditAmount,
                'refund_amount' => $refundAmount,
                'refund_account_id' => bccomp($refundAmount, '0', 2) === 1 ? $refundAccountId : null,
            ]);

            return $salesReturn->fresh(['items.product', 'sale']);
        }, attempts: 3);
    }

    private function recordCustomerRefund(SalesReturn $salesReturn, ?int $refundAccountId, string $amount, User $user): void
    {
        $account = $refundAccountId === null
            ? null
            : CashAccount::query()->whereKey($refundAccountId)->lockForUpdate()->first();

        if ($account === null || ! $account->is_active) {
            throw ValidationException::withMessages(['refund_account_id' => 'Pilih akun kas atau bank untuk pengembalian dana pelanggan.']);
        }

        if (bccomp($account->balance, $amount, 2) === -1) {
            throw ValidationException::withMessages(['refund_account_id' => 'Saldo akun tidak mencukupi untuk pengembalian dana pelanggan.']);
        }

        $account->decrement('balance', $amount);
        CashTransaction::create([
            'cash_account_id' => $account->id,
            'transaction_date' => $salesReturn->return_date,
            'direction' => 'out',
            'amount' => $amount,
            'source_type' => $salesReturn->getMorphClass(),
            'source_id' => $salesReturn->id,
            'reference_number' => $salesReturn->number,
            'description' => "Refund pelanggan {$salesReturn->number}",
            'user_id' => $user->id,
        ]);
    }
}
