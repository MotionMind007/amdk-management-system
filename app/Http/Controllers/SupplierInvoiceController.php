<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierInvoiceRequest;
use App\Models\GoodsReceipt;
use App\Models\SupplierInvoice;
use App\Services\AuditService;
use App\Services\SupplierInvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
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
        AuditService $audit,
    ): RedirectResponse {
        $data = $request->validated();
        $proofPath = $request->file('proof')?->store('supplier-invoices');

        try {
            $invoice = $invoices->create($goodsReceipt, [...$data, 'proof_path' => $proofPath], $request->user(), $data['submission_token'] ?? null);
            $audit->record(
                $request,
                'CREATE',
                'SupplierInvoice',
                $invoice,
                newValues: $invoice->toArray(),
                description: "Mencatat tagihan supplier {$invoice->number} (faktur {$invoice->supplier_invoice_number}) untuk {$goodsReceipt->number} senilai Rp ".number_format((float) $invoice->total, 0, ',', '.').'.',
            );
        } catch (Throwable $exception) {
            if ($proofPath !== null) {
                Storage::delete($proofPath);
            }

            throw $exception;
        }

        return redirect()->route('finance.index')->with('success', "Tagihan supplier {$invoice->number} berhasil dicatat.");
    }

    public function proof(SupplierInvoice $supplierInvoice): StreamedResponse
    {
        abort_if($supplierInvoice->proof_path === null || ! Storage::exists($supplierInvoice->proof_path), 404);

        return Storage::download($supplierInvoice->proof_path, 'faktur-'.$supplierInvoice->supplier_invoice_number.'.'.pathinfo($supplierInvoice->proof_path, PATHINFO_EXTENSION));
    }
}
