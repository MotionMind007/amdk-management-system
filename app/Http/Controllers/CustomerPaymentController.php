<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerPaymentRequest;
use App\Models\CashAccount;
use App\Models\CustomerPayment;
use App\Models\Sale;
use App\Services\AuditService;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class CustomerPaymentController extends Controller
{
    public function create(Sale $sale): View
    {
        $sale->load(['customer', 'payments.cashAccount']);

        return view('finance.customer-payment', ['sale' => $sale, 'accounts' => CashAccount::query()->where('is_active', true)->get()]);
    }

    public function store(CustomerPaymentRequest $request, Sale $sale, PaymentService $payments, AuditService $audit): RedirectResponse
    {
        $data = $request->validated();
        $proofPath = $request->file('proof')?->store('customer-payments');

        try {
            $payment = $payments->receiveCustomerPayment(
                $sale,
                CashAccount::findOrFail($data['cash_account_id']),
                (string) $data['amount'],
                $request->user(),
                $data['payment_date'],
                $data['notes'] ?? null,
                $proofPath,
                $data['submission_token'] ?? null,
            );
            $audit->record($request, 'PAYMENT', 'Receivable', $payment, newValues: $payment->toArray());
        } catch (Throwable $exception) {
            if ($proofPath !== null) {
                Storage::delete($proofPath);
            }

            throw $exception;
        }

        return redirect()->route('sales.index')->with('success', 'Pembayaran pelanggan berhasil dicatat.');
    }

    public function proof(CustomerPayment $customerPayment): StreamedResponse
    {
        abort_if($customerPayment->proof_path === null || ! Storage::exists($customerPayment->proof_path), 404);

        return Storage::download($customerPayment->proof_path, 'bukti-'.$customerPayment->number.'.'.pathinfo($customerPayment->proof_path, PATHINFO_EXTENSION));
    }
}
