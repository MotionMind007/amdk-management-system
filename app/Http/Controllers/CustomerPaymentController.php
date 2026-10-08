<?php

namespace App\Http\Controllers;

use App\Models\CashAccount;
use App\Models\Sale;
use App\Services\AuditService;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CustomerPaymentController extends Controller
{
    public function create(Sale $sale): View
    {
        $sale->load('customer');

        return view('finance.customer-payment', ['sale' => $sale, 'accounts' => CashAccount::query()->where('is_active', true)->get()]);
    }

    public function store(Request $request, Sale $sale, PaymentService $payments, AuditService $audit): RedirectResponse
    {
        $data = $request->validate(['cash_account_id' => ['required', 'exists:cash_accounts,id'], 'amount' => ['required', 'numeric', 'gt:0'], 'notes' => ['nullable', 'string', 'max:1000']]);
        DB::transaction(function () use ($request, $sale, $data, $payments, $audit): void {
            $payment = $payments->receiveCustomerPayment($sale, CashAccount::findOrFail($data['cash_account_id']), (string) $data['amount'], $request->user());
            $payment->update(['notes' => $data['notes'] ?? null]);
            $audit->record($request, 'PAYMENT', 'Receivable', $payment, newValues: $payment->toArray());
        });

        return redirect()->route('sales.index')->with('success', 'Pembayaran pelanggan berhasil dicatat.');
    }
}
