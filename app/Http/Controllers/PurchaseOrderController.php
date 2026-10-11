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
                'goodsReceipts:id,purchase_order_id,number,receipt_date,status,proof_path,total',
                'goodsReceipts.supplierInvoice:id,goods_receipt_id,number,supplier_invoice_number,status,total,outstanding_amount',
                'purchaseReturns:id,purchase_order_id,number,return_date,total',
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
        return $this->form(new PurchaseOrder);
    }

    public function store(PurchaseOrderRequest $request, DocumentNumberService $numbers, AuditService $audit): RedirectResponse
    {
        $data = $request->validated();
        $purchaseOrder = DB::transaction(function () use ($request, $data, $numbers, $audit): PurchaseOrder {
            $purchaseOrder = PurchaseOrder::create([
                'number' => $numbers->next('PO'),
                'created_by' => $request->user()->id,
                'status' => 'draft',
                ...$this->headerValues($data),
                'total' => $this->calculateTotal($data['items']),
            ]);
            $this->replaceItems($purchaseOrder, $data['items']);
            $audit->record($request, 'CREATE', 'PurchaseOrder', $purchaseOrder, newValues: $purchaseOrder->load('items')->toArray());

            return $purchaseOrder;
        });

        $this->notifyApprovers($purchaseOrder, $request);

        return redirect()->route('purchasing.index')->with('success', "{$purchaseOrder->number} berhasil dibuat sebagai draft.");
    }

    public function edit(PurchaseOrder $purchaseOrder): View
    {
        $this->ensureEditable($purchaseOrder);
        $purchaseOrder->load('items');

        return $this->form($purchaseOrder);
    }

    public function update(PurchaseOrderRequest $request, PurchaseOrder $purchaseOrder, AuditService $audit): RedirectResponse
    {
        $data = $request->validated();
        $wasRejected = $purchaseOrder->status === 'rejected';

        DB::transaction(function () use ($request, $purchaseOrder, $data, $audit): void {
            $purchaseOrder = PurchaseOrder::query()->whereKey($purchaseOrder->getKey())->lockForUpdate()->firstOrFail();
            $this->ensureEditable($purchaseOrder);
            $old = $purchaseOrder->load('items')->toArray();
            $purchaseOrder->update([
                ...$this->headerValues($data),
                'total' => $this->calculateTotal($data['items']),
                'status' => 'draft',
                'approved_by' => null,
                'approved_at' => null,
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
            ]);
            $this->replaceItems($purchaseOrder, $data['items']);
            $audit->record(
                $request,
                'UPDATE',
                'PurchaseOrder',
                $purchaseOrder,
                $old,
                $purchaseOrder->fresh('items')->toArray(),
                "Memperbarui draft purchase order {$purchaseOrder->number}.",
            );
        });

        if ($wasRejected) {
            $this->notifyApprovers($purchaseOrder->fresh(), $request);
        }

        return redirect()->route('purchasing.index')->with('success', "Draft {$purchaseOrder->number} berhasil diperbarui.");
    }

    public function approve(Request $request, PurchaseOrder $purchaseOrder, AuditService $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $purchaseOrder, $audit): void {
            $purchaseOrder = PurchaseOrder::query()->whereKey($purchaseOrder->getKey())->lockForUpdate()->firstOrFail();

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

    public function reject(Request $request, PurchaseOrder $purchaseOrder, AuditService $audit): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']], ['reason.required' => 'Alasan penolakan wajib diisi.']);

        DB::transaction(function () use ($request, $purchaseOrder, $data, $audit): void {
            $purchaseOrder = PurchaseOrder::query()->whereKey($purchaseOrder->getKey())->lockForUpdate()->firstOrFail();
            if ($purchaseOrder->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Hanya Purchase Order draft yang dapat ditolak.']);
            }

            $old = $purchaseOrder->toArray();
            $purchaseOrder->update([
                'status' => 'rejected',
                'rejected_by' => $request->user()->id,
                'rejected_at' => now(),
                'rejection_reason' => $data['reason'],
            ]);
            $audit->record(
                $request,
                'REJECT',
                'PurchaseOrder',
                $purchaseOrder,
                $old,
                $purchaseOrder->fresh()->toArray(),
                "Menolak purchase order {$purchaseOrder->number}. Alasan: {$data['reason']}.",
            );
        });

        return back()->with('success', "Purchase Order {$purchaseOrder->number} ditolak dan dapat direvisi.");
    }

    public function cancel(Request $request, PurchaseOrder $purchaseOrder, AuditService $audit): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']], ['reason.required' => 'Alasan pembatalan wajib diisi.']);

        DB::transaction(function () use ($request, $purchaseOrder, $data, $audit): void {
            $purchaseOrder = PurchaseOrder::query()->whereKey($purchaseOrder->getKey())->lockForUpdate()->firstOrFail();
            if (! in_array($purchaseOrder->status, ['draft', 'rejected', 'approved'], true)) {
                throw ValidationException::withMessages(['status' => 'PO yang sudah memiliki penerimaan tidak dapat dibatalkan; gunakan retur pembelian.']);
            }

            if ($purchaseOrder->goodsReceipts()->exists()) {
                throw ValidationException::withMessages(['status' => 'PO yang sudah memiliki penerimaan tidak dapat dibatalkan; gunakan retur pembelian.']);
            }

            $old = $purchaseOrder->toArray();
            $purchaseOrder->update([
                'status' => 'cancelled',
                'cancelled_by' => $request->user()->id,
                'cancelled_at' => now(),
                'cancellation_reason' => $data['reason'],
            ]);
            $audit->record(
                $request,
                'CANCEL',
                'PurchaseOrder',
                $purchaseOrder,
                $old,
                $purchaseOrder->fresh()->toArray(),
                "Membatalkan purchase order {$purchaseOrder->number}. Alasan: {$data['reason']}.",
            );
        });

        return back()->with('success', "Purchase Order {$purchaseOrder->number} dibatalkan.");
    }

    private function form(PurchaseOrder $purchaseOrder): View
    {
        return view('purchasing.create', [
            'purchaseOrder' => $purchaseOrder,
            'suppliers' => Supplier::query()->where('status', 'active')->orderBy('name')->get(),
            'products' => Product::query()->where('is_active', true)->with('unit')->orderBy('name')->get(),
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(),
            'cashAccounts' => CashAccount::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    private function ensureEditable(PurchaseOrder $purchaseOrder): void
    {
        if (! in_array($purchaseOrder->status, ['draft', 'rejected'], true)) {
            throw ValidationException::withMessages(['status' => 'Hanya PO draft atau ditolak yang dapat diedit.']);
        }
    }

    /** @param array<int, array{quantity: mixed, unit_price: mixed}> $items */
    private function calculateTotal(array $items): string
    {
        $total = '0.00';
        foreach ($items as $item) {
            $total = bcadd($total, bcmul((string) $item['quantity'], (string) $item['unit_price'], 2), 2);
        }

        return $total;
    }

    /** @param array<int, array{product_id: mixed, quantity: mixed, unit_price: mixed}> $items */
    private function replaceItems(PurchaseOrder $purchaseOrder, array $items): void
    {
        $purchaseOrder->items()->delete();
        foreach ($items as $item) {
            $purchaseOrder->items()->create([
                'product_id' => $item['product_id'],
                'ordered_quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'line_total' => bcmul((string) $item['quantity'], (string) $item['unit_price'], 2),
            ]);
        }
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function headerValues(array $data): array
    {
        return [
            'supplier_id' => $data['supplier_id'],
            'warehouse_id' => $data['warehouse_id'],
            'order_date' => $data['order_date'],
            'expected_date' => $data['expected_date'] ?? null,
            'payment_type' => $data['payment_type'],
            'due_date' => $data['due_date'] ?? null,
            'cash_account_id' => $data['cash_account_id'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];
    }

    private function notifyApprovers(PurchaseOrder $purchaseOrder, Request $request): void
    {
        $purchaseOrder->loadMissing('supplier:id,name');
        $approvers = User::query()
            ->where('is_active', true)
            ->whereIn('role', [UserRole::Owner->value, UserRole::SuperAdministrator->value])
            ->get();
        Notification::send($approvers, new PurchaseOrderAwaitingApproval(
            $purchaseOrder->id,
            $purchaseOrder->number,
            $purchaseOrder->supplier->name,
            $request->user()->name,
            $purchaseOrder->total,
        ));
    }
}
