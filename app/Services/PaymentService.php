<?php

namespace App\Services;

use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\CustomerPayment;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(private DocumentNumberService $numberService, private AccountingPeriodService $periods, private SubmissionTokenService $tokens) {}

    public function receiveCustomerPayment(
        Sale $sale,
        CashAccount $account,
        string $amount,
        User $user,
        ?string $paymentDate = null,
        ?string $notes = null,
        ?string $proofPath = null,
        ?string $submissionToken = null,
    ): CustomerPayment {
        return DB::transaction(function () use ($sale, $account, $amount, $user, $paymentDate, $notes, $proofPath, $submissionToken): CustomerPayment {
            $sale = Sale::query()->whereKey($sale->getKey())->lockForUpdate()->firstOrFail();
            $this->tokens->claim($submissionToken, $user, 'customer-payment.create', $sale);
            $this->periods->ensureOpen($paymentDate ?? today());
            $account = CashAccount::query()->whereKey($account->getKey())->lockForUpdate()->firstOrFail();
            $this->validateActiveAccount($account);
            $this->validatePaymentAmount($amount, $sale->outstanding_amount);

            $payment = CustomerPayment::create([
                'number' => $this->numberService->next('PAY-IN'),
                'sale_id' => $sale->id,
                'cash_account_id' => $account->id,
                'payment_date' => $paymentDate ?? today(),
                'amount' => $amount,
                'notes' => $notes,
                'proof_path' => $proofPath,
                'created_by' => $user->id,
            ]);
            $sale->increment('paid_amount', $amount);
            $sale->decrement('outstanding_amount', $amount);
            $sale->refresh();
            $sale->update(['status' => bccomp($sale->outstanding_amount, '0', 2) === 0 ? 'paid' : 'partially_paid']);
            $account->increment('balance', $amount);
            CashTransaction::create(['cash_account_id' => $account->id, 'transaction_date' => $payment->payment_date, 'direction' => 'in', 'amount' => $amount, 'source_type' => $payment->getMorphClass(), 'source_id' => $payment->id, 'reference_number' => $payment->number, 'description' => "Pembayaran {$sale->number}".($notes ? ": {$notes}" : ''), 'user_id' => $user->id]);

            return $payment;
        });
    }

    public function paySupplier(
        SupplierInvoice|PurchaseOrder $payable,
        CashAccount $account,
        string $amount,
        User $user,
        ?string $paymentDate = null,
        ?string $notes = null,
        ?string $proofPath = null,
        ?string $submissionToken = null,
    ): SupplierPayment {
        return DB::transaction(function () use ($payable, $account, $amount, $user, $paymentDate, $notes, $proofPath, $submissionToken): SupplierPayment {
            $supplierInvoice = $payable instanceof SupplierInvoice
                ? SupplierInvoice::query()->whereKey($payable->getKey())->lockForUpdate()->firstOrFail()
                : null;
            $purchaseOrder = PurchaseOrder::query()
                ->whereKey($supplierInvoice?->purchase_order_id ?? $payable->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $this->tokens->claim($submissionToken, $user, 'supplier-payment.create', $supplierInvoice ?? $purchaseOrder);
            $this->periods->ensureOpen($paymentDate ?? today());
            $account = CashAccount::query()->whereKey($account->getKey())->lockForUpdate()->firstOrFail();
            $this->validateActiveAccount($account);
            $this->validatePaymentAmount($amount, $supplierInvoice?->outstanding_amount ?? $purchaseOrder->outstanding_amount);
            if (bccomp($account->balance, $amount, 2) === -1) {
                throw ValidationException::withMessages(['amount' => 'Saldo kas atau bank tidak mencukupi.']);
            }

            $payment = SupplierPayment::create([
                'number' => $this->numberService->next('PAY-OUT'),
                'purchase_order_id' => $purchaseOrder->id,
                'supplier_invoice_id' => $supplierInvoice?->id,
                'cash_account_id' => $account->id,
                'payment_date' => $paymentDate ?? today(),
                'amount' => $amount,
                'notes' => $notes,
                'proof_path' => $proofPath,
                'created_by' => $user->id,
            ]);

            if ($supplierInvoice !== null) {
                $supplierInvoice->increment('paid_amount', $amount);
                $supplierInvoice->decrement('outstanding_amount', $amount);
                $supplierInvoice->refresh();
                $supplierInvoice->update(['status' => bccomp($supplierInvoice->outstanding_amount, '0', 2) === 0 ? 'paid' : 'partially_paid']);
            }

            $purchaseOrder->increment('paid_amount', $amount);
            $purchaseOrder->decrement('outstanding_amount', $amount);
            $account->decrement('balance', $amount);
            CashTransaction::create(['cash_account_id' => $account->id, 'transaction_date' => $payment->payment_date, 'direction' => 'out', 'amount' => $amount, 'source_type' => $payment->getMorphClass(), 'source_id' => $payment->id, 'reference_number' => $payment->number, 'description' => 'Pembayaran '.($supplierInvoice?->number ?? $purchaseOrder->number).($notes ? ": {$notes}" : ''), 'user_id' => $user->id]);

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

    private function validateActiveAccount(CashAccount $account): void
    {
        if (! $account->is_active) {
            throw ValidationException::withMessages(['cash_account_id' => 'Akun kas atau bank tidak aktif atau tidak tersedia.']);
        }
    }
}
