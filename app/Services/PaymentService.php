<?php

namespace App\Services;

use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\CustomerPayment;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(private DocumentNumberService $numberService) {}

    public function receiveCustomerPayment(Sale $sale, CashAccount $account, string $amount, User $user): CustomerPayment
    {
        return DB::transaction(function () use ($sale, $account, $amount, $user): CustomerPayment {
            $sale = Sale::query()->whereKey($sale->getKey())->lockForUpdate()->firstOrFail();
            $account = CashAccount::query()->whereKey($account->getKey())->lockForUpdate()->firstOrFail();
            $this->validatePaymentAmount($amount, $sale->outstanding_amount);

            $payment = CustomerPayment::create(['number' => $this->numberService->next('PAY-IN'), 'sale_id' => $sale->id, 'cash_account_id' => $account->id, 'payment_date' => today(), 'amount' => $amount, 'created_by' => $user->id]);
            $sale->increment('paid_amount', $amount);
            $sale->decrement('outstanding_amount', $amount);
            $sale->refresh();
            $sale->update(['status' => bccomp($sale->outstanding_amount, '0', 2) === 0 ? 'paid' : 'partially_paid']);
            $account->increment('balance', $amount);
            CashTransaction::create(['cash_account_id' => $account->id, 'transaction_date' => today(), 'direction' => 'in', 'amount' => $amount, 'source_type' => $payment->getMorphClass(), 'source_id' => $payment->id, 'reference_number' => $payment->number, 'description' => "Pembayaran {$sale->number}", 'user_id' => $user->id]);

            return $payment;
        });
    }

    public function paySupplier(PurchaseOrder $purchaseOrder, CashAccount $account, string $amount, User $user): SupplierPayment
    {
        return DB::transaction(function () use ($purchaseOrder, $account, $amount, $user): SupplierPayment {
            $purchaseOrder = PurchaseOrder::query()->whereKey($purchaseOrder->getKey())->lockForUpdate()->firstOrFail();
            $account = CashAccount::query()->whereKey($account->getKey())->lockForUpdate()->firstOrFail();
            $this->validatePaymentAmount($amount, $purchaseOrder->outstanding_amount);
            if (bccomp($account->balance, $amount, 2) === -1) {
                throw ValidationException::withMessages(['amount' => 'Saldo kas atau bank tidak mencukupi.']);
            }

            $payment = SupplierPayment::create(['number' => $this->numberService->next('PAY-OUT'), 'purchase_order_id' => $purchaseOrder->id, 'cash_account_id' => $account->id, 'payment_date' => today(), 'amount' => $amount, 'created_by' => $user->id]);
            $purchaseOrder->increment('paid_amount', $amount);
            $purchaseOrder->decrement('outstanding_amount', $amount);
            $account->decrement('balance', $amount);
            CashTransaction::create(['cash_account_id' => $account->id, 'transaction_date' => today(), 'direction' => 'out', 'amount' => $amount, 'source_type' => $payment->getMorphClass(), 'source_id' => $payment->id, 'reference_number' => $payment->number, 'description' => "Pembayaran {$purchaseOrder->number}", 'user_id' => $user->id]);

            return $payment;
        });
    }

    private function validatePaymentAmount(string $amount, string $outstanding): void
    {
        if (! is_numeric($amount) || bccomp($amount, '0', 2) !== 1) {
            throw ValidationException::withMessages(['amount' => 'Nominal pembayaran harus lebih besar dari nol.']);
        }
        if (bccomp($amount, $outstanding, 2) === 1) {
            throw ValidationException::withMessages(['amount' => 'Pembayaran tidak boleh melebihi saldo outstanding.']);
        }
    }
}
