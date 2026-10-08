<?php

namespace App\Http\Controllers;

use App\Models\CashAccount;
use App\Models\PurchaseOrder;
use App\Services\AuditService;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SupplierPaymentController extends Controller
{
    public function create(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load('supplier');

        return view('finance.supplier-payment', ['purchaseOrder' => $purchaseOrder, 'accounts' => CashAccount::query()->where('is_active', true)->get()]);
    }

    public function store(Request $request, PurchaseOrder $purchaseOrder, PaymentService $payments, AuditService $audit): RedirectResponse
    {
        $data = $request->validate(['cash_account_id' => ['required', 'exists:cash_accounts,id'], 'amount' => ['required', 'numeric', 'gt:0'], 'notes' => ['nullable', 'string', 'max:1000']]);
        DB::transaction(function () use ($request, $purchaseOrder, $data, $payments, $audit): void {
            $payment = $payments->paySupplier($purchaseOrder, CashAccount::findOrFail($data['cash_account_id']), (string) $data['amount'], $request->user());
            $payment->update(['notes' => $data['notes'] ?? null]);
            $audit->record($request, 'PAYMENT', 'Payable', $payment, newValues: $payment->toArray());
        });

        return redirect()->route('purchasing.index')->with('success', 'Pembayaran supplier berhasil dicatat.');
    }
}
