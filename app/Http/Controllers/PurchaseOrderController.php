<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseOrderRequest;
use App\Models\CashAccount;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\GoodsReceiptExpected;
use App\Notifications\PurchaseOrderAwaitingApproval;
use App\Services\AuditService;
use App\Services\DocumentNumberService;
use App\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function index(): View
    {
        $purchaseOrders = PurchaseOrder::query()
            ->with([
                'goodsReceipts:id,purchase_order_id,number,receipt_date,status,proof_path',
                'supplier:id,name',
                'warehouse:id,name',
            ])
            ->latest('order_date')
            ->latest('id')
            ->paginate(20);

        return view('purchasing.index', compact('purchaseOrders'));
    }

    public function create(): View
    {
        return view('purchasing.create', [
            'suppliers' => Supplier::query()->where('status', 'active')->orderBy('name')->get(),
            'products' => Product::query()->where('is_active', true)->with('unit')->orderBy('name')->get(),
            'warehouses' => Warehouse::query()->where('is_active', true)->get(),
            'cashAccounts' => CashAccount::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(PurchaseOrderRequest $request, DocumentNumberService $numbers, AuditService $audit): RedirectResponse
    {
        $data = $request->validated();
        $po = DB::transaction(function () use ($request, $data, $numbers, $audit): PurchaseOrder {
            $total = '0.00';
            foreach ($data['items'] as $item) {
                $total = bcadd($total, bcmul($item['quantity'], $item['unit_price'], 2), 2);
            }
            $po = PurchaseOrder::create(['number' => $numbers->next('PO'), 'supplier_id' => $data['supplier_id'], 'warehouse_id' => $data['warehouse_id'], 'order_date' => $data['order_date'], 'expected_date' => $data['expected_date'] ?? null, 'payment_type' => $data['payment_type'], 'due_date' => $data['due_date'] ?? null, 'cash_account_id' => $data['cash_account_id'] ?? null, 'status' => 'draft', 'total' => $total, 'notes' => $data['notes'] ?? null, 'created_by' => $request->user()->id]);
            foreach ($data['items'] as $item) {
                $po->items()->create(['product_id' => $item['product_id'], 'ordered_quantity' => $item['quantity'], 'unit_price' => $item['unit_price'], 'line_total' => bcmul($item['quantity'], $item['unit_price'], 2)]);
            }
            $audit->record($request, 'CREATE', 'PurchaseOrder', $po, newValues: $po->load('items')->toArray());

            return $po;
        });

        $po->loadMissing('supplier:id,name');
        $approvers = User::query()
            ->where('is_active', true)
            ->whereIn('role', [UserRole::Owner->value, UserRole::SuperAdministrator->value])
            ->get();
        Notification::send($approvers, new PurchaseOrderAwaitingApproval(
            $po->id,
            $po->number,
            $po->supplier->name,
            $request->user()->name,
            $po->total,
        ));

        return redirect()->route('purchasing.index')->with('success', "{$po->number} berhasil dibuat sebagai draft.");
    }

    public function approve(Request $request, PurchaseOrder $purchaseOrder, AuditService $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $purchaseOrder, $audit): void {
            $purchaseOrder = PurchaseOrder::query()
                ->whereKey($purchaseOrder->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($purchaseOrder->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Hanya Purchase Order draft yang dapat disetujui.']);
            }

            $purchaseOrder->load(['supplier', 'warehouse', 'items.product']);

            if ($purchaseOrder->supplier === null || $purchaseOrder->supplier->status !== 'active') {
                throw ValidationException::withMessages(['supplier_id' => 'Supplier tidak aktif atau tidak tersedia.']);
            }

            if (! $purchaseOrder->warehouse->is_active) {
                throw ValidationException::withMessages(['warehouse_id' => 'Gudang tidak aktif atau tidak tersedia.']);
            }

            if ($purchaseOrder->items->contains(fn ($item): bool => $item->product === null || ! $item->product->is_active)) {
                throw ValidationException::withMessages(['items' => 'PO memuat produk atau bahan yang tidak aktif atau tidak tersedia.']);
            }

            $old = $purchaseOrder->toArray();
            $purchaseOrder->update(['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()]);
            $audit->record($request, 'APPROVE', 'PurchaseOrder', $purchaseOrder, $old, $purchaseOrder->fresh()->toArray());
        });

        $purchaseOrder->refresh()->loadMissing('supplier:id,name');
        $warehouseUsers = User::query()
            ->where('is_active', true)
            ->where('role', UserRole::Warehouse->value)
            ->get();
        Notification::send($warehouseUsers, new GoodsReceiptExpected(
            $purchaseOrder->id,
            $purchaseOrder->number,
            $purchaseOrder->supplier->name,
            $request->user()->name,
            $purchaseOrder->expected_date?->format('d/m/Y'),
        ));

        return back()->with('success', 'Purchase Order disetujui dan siap diterima.');
    }
}
