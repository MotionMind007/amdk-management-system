<?php

namespace App\Services;

use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\SupplierInvoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierInvoiceService
{
    public function __construct(private DocumentNumberService $numberService, private AccountingPeriodService $periods, private SubmissionTokenService $tokens) {}

    /** @param array{supplier_invoice_number: string, invoice_date: string, due_date?: string|null, total: mixed, notes?: string|null, proof_path?: string|null} $data */
    public function create(GoodsReceipt $goodsReceipt, array $data, User $user, ?string $submissionToken = null): SupplierInvoice
    {
        return DB::transaction(function () use ($goodsReceipt, $data, $user, $submissionToken): SupplierInvoice {
            $goodsReceipt = GoodsReceipt::query()->whereKey($goodsReceipt->getKey())->lockForUpdate()->firstOrFail();
            $this->tokens->claim($submissionToken, $user, 'supplier-invoice.create', $goodsReceipt);
            $this->periods->ensureOpen($data['invoice_date']);
            if ($goodsReceipt->status !== 'posted') {
                throw ValidationException::withMessages(['goods_receipt_id' => 'Hanya penerimaan terposting yang dapat dibuatkan tagihan supplier.']);
            }

            if ($goodsReceipt->supplierInvoice()->exists()) {
                throw ValidationException::withMessages(['goods_receipt_id' => 'Penerimaan ini sudah memiliki tagihan supplier.']);
            }

            $purchaseOrder = PurchaseOrder::query()->whereKey($goodsReceipt->purchase_order_id)->lockForUpdate()->firstOrFail();
            $total = bcadd((string) $data['total'], '0', 2);
            $invoice = SupplierInvoice::create([
                'number' => $this->numberService->next('BILL'),
                'goods_receipt_id' => $goodsReceipt->id,
                'purchase_order_id' => $purchaseOrder->id,
                'supplier_invoice_number' => $data['supplier_invoice_number'],
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                'status' => 'unpaid',
                'total' => $total,
                'outstanding_amount' => $total,
                'notes' => $data['notes'] ?? null,
                'proof_path' => $data['proof_path'] ?? null,
                'created_by' => $user->id,
            ]);

            $this->syncPurchaseOrderBalances($purchaseOrder);

            return $invoice->fresh(['goodsReceipt.items.product', 'purchaseOrder.supplier']);
        });
    }

    public function syncPurchaseOrderBalances(PurchaseOrder $purchaseOrder): void
    {
        $totals = $purchaseOrder->supplierInvoices()
            ->selectRaw('COALESCE(SUM(paid_amount), 0) as paid, COALESCE(SUM(outstanding_amount), 0) as outstanding')
            ->first();

        $purchaseOrder->update([
            'paid_amount' => (string) ($totals?->paid ?? 0),
            'outstanding_amount' => (string) ($totals?->outstanding ?? 0),
        ]);
    }
}
