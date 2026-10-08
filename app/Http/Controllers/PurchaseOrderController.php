<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\AuditService;
use App\Services\DocumentNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function index(): View
    {
        $purchaseOrders = PurchaseOrder::query()->with(['supplier:id,name', 'warehouse:id,name'])->latest('order_date')->latest('id')->paginate(20);

        return view('purchasing.index', compact('purchaseOrders'));
    }

    public function create(): View
    {
        return view('purchasing.create', ['suppliers' => Supplier::query()->where('status', 'active')->orderBy('name')->get(), 'products' => Product::query()->where('is_active', true)->with('unit')->orderBy('name')->get(), 'warehouses' => Warehouse::query()->where('is_active', true)->get()]);
    }

    public function store(Request $request, DocumentNumberService $numbers, AuditService $audit): RedirectResponse
    {
        $data = $request->validate(['supplier_id' => ['required', 'exists:suppliers,id'], 'warehouse_id' => ['required', 'exists:warehouses,id'], 'order_date' => ['required', 'date'], 'expected_date' => ['nullable', 'date', 'after_or_equal:order_date'], 'notes' => ['nullable', 'string', 'max:2000'], 'items' => ['required', 'array', 'min:1'], 'items.*.product_id' => ['required', 'distinct', 'exists:products,id'], 'items.*.quantity' => ['required', 'numeric', 'gt:0'], 'items.*.unit_price' => ['required', 'numeric', 'min:0']]);
        $po = DB::transaction(function () use ($request, $data, $numbers, $audit): PurchaseOrder {
            $total = '0.00';
            foreach ($data['items'] as $item) {
                $total = bcadd($total, bcmul($item['quantity'], $item['unit_price'], 2), 2);
            }
            $po = PurchaseOrder::create(['number' => $numbers->next('PO'), 'supplier_id' => $data['supplier_id'], 'warehouse_id' => $data['warehouse_id'], 'order_date' => $data['order_date'], 'expected_date' => $data['expected_date'] ?? null, 'status' => 'draft', 'total' => $total, 'notes' => $data['notes'] ?? null, 'created_by' => $request->user()->id]);
            foreach ($data['items'] as $item) {
                $po->items()->create(['product_id' => $item['product_id'], 'ordered_quantity' => $item['quantity'], 'unit_price' => $item['unit_price'], 'line_total' => bcmul($item['quantity'], $item['unit_price'], 2)]);
            }
            $audit->record($request, 'CREATE', 'PurchaseOrder', $po, newValues: $po->load('items')->toArray());

            return $po;
        });

        return redirect()->route('purchasing.index')->with('success', "{$po->number} berhasil dibuat sebagai draft.");
    }

    public function approve(Request $request, PurchaseOrder $purchaseOrder, AuditService $audit): RedirectResponse
    {
        abort_unless($purchaseOrder->status === 'draft', 422);
        DB::transaction(function () use ($request, $purchaseOrder, $audit): void {
            $old = $purchaseOrder->toArray();
            $purchaseOrder->update(['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()]);
            $audit->record($request, 'APPROVE', 'PurchaseOrder', $purchaseOrder, $old, $purchaseOrder->fresh()->toArray());
        });

        return back()->with('success', 'Purchase Order disetujui dan siap diterima.');
    }
}
