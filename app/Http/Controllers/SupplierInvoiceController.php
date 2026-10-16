<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierInvoiceRequest;
use App\Models\CashAccount;
use App\Models\GoodsReceipt;
use App\Models\SupplierInvoice;
use App\Services\AuditService;
use App\Services\PaymentService;
use App\Services\SupplierInvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SupplierInvoiceController extends Controller
{
    public function create(GoodsReceipt $goodsReceipt): View
    {
        abort_unless($goodsReceipt->status === 'posted', 422);
        abort_if($goodsReceipt->supplierInvoice()->exists(), 422);
        $goodsReceipt->load(['purchaseOrder.supplier', 'items.product.unit']);

        return view('purchasing.supplier-invoices.create', compact('goodsReceipt'));
    }

    public function store(
        StoreSupplierInvoiceRequest $request,
        GoodsReceipt $goodsReceipt,
        SupplierInvoiceService $invoices,
        PaymentService $payments,
        AuditService $audit,
    ): RedirectResponse {
        $data = $request->validated();
        $proofPath = $request->file('proof')?->store('supplier-invoices');

        try {
            $invoice = DB::transaction(function () use ($request, $goodsReceipt, $invoices, $payments, $audit, $data, $proofPath): SupplierInvoice {
                $invoice = $invoices->create($goodsReceipt, [...$data, 'proof_path' => $proofPath], $request->user(), $data['submission_token'] ?? null);
                $audit->record(
                    $request,
                    'CREATE',
                    'SupplierInvoice',
                    $invoice,
                    newValues: $invoice->toArray(),
                    description: "Mencatat tagihan supplier {$invoice->number} (faktur {$invoice->supplier_invoice_number}) untuk {$goodsReceipt->number} senilai Rp ".number_format((float) $invoice->total, 0, ',', '.').'.',
                );

                if ($invoice->purchaseOrder->payment_type === 'cash') {
                    $cashAccount = CashAccount::query()->find($invoice->purchaseOrder->cash_account_id);

                    if ($cashAccount === null) {
                        throw ValidationException::withMessages(['cash_account_id' => 'Akun pembayaran pembelian cash tidak tersedia.']);
                    }

                    $notes = "Pembayaran otomatis pembelian cash {$invoice->purchaseOrder->number}";
                    $payment = $payments->paySupplier(
                        $invoice,
                        $cashAccount,
                        (string) $invoice->total,
                        $request->user(),
                        $invoice->invoice_date->format('Y-m-d'),
                        $notes,
                    );
                    $audit->record(
                        $request,
                        'PAYMENT',
                        'Payable',
                        $payment,
                        newValues: $payment->toArray(),
                        description: "Membayar otomatis tagihan {$invoice->number} dari {$cashAccount->name} senilai Rp ".number_format((float) $payment->amount, 0, ',', '.').'.',
                    );
                }

                return $invoice;
            });
        } catch (Throwable $exception) {
            if ($proofPath !== null) {
                Storage::delete($proofPath);
            }

            throw $exception;
        }

        $message = $invoice->purchaseOrder->payment_type === 'cash'
            ? "Tagihan supplier {$invoice->number} berhasil dicatat dan dibayar otomatis."
            : "Tagihan supplier {$invoice->number} berhasil dicatat.";

        return redirect()->route('finance.index')->with('success', $message);
    }

    public function proof(SupplierInvoice $supplierInvoice): StreamedResponse
    {
        abort_if($supplierInvoice->proof_path === null || ! Storage::exists($supplierInvoice->proof_path), 404);

        return Storage::download($supplierInvoice->proof_path, 'faktur-'.$supplierInvoice->supplier_invoice_number.'.'.pathinfo($supplierInvoice->proof_path, PATHINFO_EXTENSION));
    }
}
