<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            $legacySales = DB::table('sales')
                ->whereNotIn('status', ['draft', 'cancelled'])
                ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('delivery_notes')->whereColumn('delivery_notes.sale_id', 'sales.id'))
                ->orderBy('id')
                ->get();

            foreach ($legacySales as $sale) {
                $deliveryNoteId = DB::table('delivery_notes')->insertGetId([
                    'number' => 'LEGACY-SJ-'.$sale->id,
                    'sale_id' => $sale->id,
                    'warehouse_id' => $sale->warehouse_id,
                    'delivery_date' => $sale->sale_date,
                    'status' => 'posted',
                    'notes' => 'Migrasi pengiriman dari transaksi sebelum fitur surat jalan.',
                    'created_by' => $sale->created_by,
                    'posted_by' => $sale->created_by,
                    'posted_at' => $sale->posted_at ?? $sale->created_at ?? now(),
                    'created_at' => $sale->created_at ?? now(),
                    'updated_at' => now(),
                ]);

                foreach (DB::table('sale_items')->where('sale_id', $sale->id)->get() as $item) {
                    DB::table('delivery_note_items')->insert([
                        'delivery_note_id' => $deliveryNoteId,
                        'sale_item_id' => $item->id,
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $purchaseOrders = DB::table('purchase_orders')
                ->whereExists(fn ($query) => $query->selectRaw('1')->from('goods_receipts')->whereColumn('goods_receipts.purchase_order_id', 'purchase_orders.id'))
                ->orderBy('id')
                ->get();

            foreach ($purchaseOrders as $purchaseOrder) {
                $remainingPaid = (float) $purchaseOrder->paid_amount;
                $invoiceIds = [];
                $receipts = DB::table('goods_receipts')->where('purchase_order_id', $purchaseOrder->id)->orderBy('id')->get();

                foreach ($receipts as $receipt) {
                    if (DB::table('supplier_invoices')->where('goods_receipt_id', $receipt->id)->exists()) {
                        continue;
                    }

                    $returned = (float) DB::table('purchase_return_items')
                        ->join('goods_receipt_items', 'goods_receipt_items.id', '=', 'purchase_return_items.goods_receipt_item_id')
                        ->where('goods_receipt_items.goods_receipt_id', $receipt->id)
                        ->sum('purchase_return_items.line_total');
                    $total = max(0, (float) $receipt->total - $returned);
                    if ($total <= 0) {
                        continue;
                    }

                    $paid = min($total, $remainingPaid);
                    $outstanding = $total - $paid;
                    $invoiceIds[] = DB::table('supplier_invoices')->insertGetId([
                        'number' => 'LEGACY-BILL-'.$receipt->id,
                        'goods_receipt_id' => $receipt->id,
                        'purchase_order_id' => $purchaseOrder->id,
                        'supplier_invoice_number' => 'LEGACY-'.$receipt->number,
                        'invoice_date' => $receipt->receipt_date,
                        'due_date' => $purchaseOrder->due_date,
                        'status' => $outstanding <= 0 ? 'paid' : ($paid > 0 ? 'partially_paid' : 'unpaid'),
                        'total' => $total,
                        'paid_amount' => $paid,
                        'outstanding_amount' => $outstanding,
                        'notes' => 'Dibentuk otomatis dari data penerimaan lama.',
                        'created_by' => $receipt->created_by,
                        'created_at' => $receipt->created_at ?? now(),
                        'updated_at' => now(),
                    ]);
                    $remainingPaid -= $paid;
                }

                foreach (DB::table('supplier_payments')->where('purchase_order_id', $purchaseOrder->id)->whereNull('supplier_invoice_id')->orderBy('id')->get() as $index => $payment) {
                    if ($invoiceIds === []) {
                        break;
                    }

                    DB::table('supplier_payments')->where('id', $payment->id)->update([
                        'supplier_invoice_id' => $invoiceIds[min($index, count($invoiceIds) - 1)],
                    ]);
                }

                $totals = DB::table('supplier_invoices')
                    ->where('purchase_order_id', $purchaseOrder->id)
                    ->selectRaw('COALESCE(SUM(paid_amount), 0) as paid, COALESCE(SUM(outstanding_amount), 0) as outstanding')
                    ->first();
                DB::table('purchase_orders')->where('id', $purchaseOrder->id)->update([
                    'paid_amount' => $totals->paid,
                    'outstanding_amount' => $totals->outstanding,
                ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::transaction(function (): void {
            DB::table('supplier_payments')->whereNotNull('supplier_invoice_id')->update(['supplier_invoice_id' => null]);
            DB::table('supplier_invoices')->where('number', 'like', 'LEGACY-BILL-%')->delete();

            $deliveryNoteIds = DB::table('delivery_notes')->where('number', 'like', 'LEGACY-SJ-%')->pluck('id');
            DB::table('delivery_note_items')->whereIn('delivery_note_id', $deliveryNoteIds)->delete();
            DB::table('delivery_notes')->whereIn('id', $deliveryNoteIds)->delete();
        });
    }
};
