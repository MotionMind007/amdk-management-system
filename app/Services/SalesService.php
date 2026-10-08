<?php

namespace App\Services;

use App\Models\CashAccount;
use App\Models\Sale;
use App\Models\User;
use App\StockMovementType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesService
{
    public function __construct(
        private InventoryService $inventoryService,
        private PaymentService $paymentService,
    ) {}

    public function post(Sale $sale, User $user): Sale
    {
        return DB::transaction(function () use ($sale, $user): Sale {
            $sale = Sale::query()->whereKey($sale->getKey())->lockForUpdate()->firstOrFail();
            if ($sale->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Hanya penjualan draft yang dapat diposting.']);
            }

            $sale->load(['items.product', 'warehouse']);
            if ($sale->items->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Minimal satu produk harus diisi.']);
            }

            $total = '0.00';
            foreach ($sale->items as $item) {
                $lineTotal = bcmul($item->quantity, $item->unit_price, 2);
                $item->update(['line_total' => $lineTotal]);
                $total = bcadd($total, $lineTotal, 2);
                $this->inventoryService->decrease($item->product, $sale->warehouse, $item->quantity, StockMovementType::Sale, $user, $sale, $sale->number);
            }

            $sale->update(['status' => 'unpaid', 'total' => $total, 'outstanding_amount' => $total, 'posted_at' => now()]);

            if ($sale->payment_type === 'cash') {
                $cashAccount = CashAccount::query()
                    ->whereKey($sale->cash_account_id)
                    ->where('is_active', true)
                    ->first();

                if ($cashAccount === null) {
                    throw ValidationException::withMessages(['cash_account_id' => 'Akun kas atau bank untuk penjualan cash tidak tersedia.']);
                }

                $this->paymentService->receiveCustomerPayment($sale, $cashAccount, $total, $user);
            }

            return $sale->fresh('items');
        });
    }
}
