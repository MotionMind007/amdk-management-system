<?php

namespace App\Http\Controllers;

use App\Http\Requests\SupplierPaymentRequest;
use App\Models\CashAccount;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Services\AuditService;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SupplierPaymentController extends Controller
{
    public function create(SupplierInvoice $supplierInvoice): View
    {
        $supplierInvoice->load(['purchaseOrder.supplier', 'payments.cashAccount']);

        return view('finance.supplier-payment', ['supplierInvoice' => $supplierInvoice, 'accounts' => CashAccount::query()->where('is_active', true)->get()]);
    }

    public function store(SupplierPaymentRequest $request, SupplierInvoice $supplierInvoice, PaymentService $payments, AuditService $audit): RedirectResponse
    {
        $data = $request->validated();
        $proofPath = $request->file('proof')?->store('supplier-payments');

        try {
            $payment = $payments->paySupplier(
                $supplierInvoice,
                CashAccount::findOrFail($data['cash_account_id']),
                (string) $data['amount'],
                $request->user(),
                $data['payment_date'],
                $data['notes'] ?? null,
                $proofPath,
                $data['submission_token'] ?? null,
            );
            $audit->record($request, 'PAYMENT', 'Payable', $payment, newValues: $payment->toArray());
        } catch (Throwable $exception) {
            if ($proofPath !== null) {
                Storage::delete($proofPath);
            }

            throw $exception;
        }

        return redirect()->route('purchasing.index')->with('success', 'Pembayaran supplier berhasil dicatat.');
    }

    public function proof(SupplierPayment $supplierPayment): StreamedResponse
    {
        abort_if($supplierPayment->proof_path === null || ! Storage::exists($supplierPayment->proof_path), 404);

        return Storage::download($supplierPayment->proof_path, 'bukti-'.$supplierPayment->number.'.'.pathinfo($supplierPayment->proof_path, PATHINFO_EXTENSION));
    }
}
