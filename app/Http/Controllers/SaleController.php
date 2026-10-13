<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaleRequest;
use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\SaleAwaitingDelivery;
use App\ProductType;
use App\Services\AuditService;
use App\Services\DocumentNumberService;
use App\Services\SalesService;
use App\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SaleController extends Controller
{
    public function index(): View
    {
        $sales = Sale::query()
            ->with([
                'customer:id,name',
                'returns:id,sale_id,number,return_date,total',
                'deliveryNotes:id,sale_id,number,delivery_date',
                'payments:id,sale_id,number,payment_date,amount,proof_path',
            ])
            ->latest('sale_date')
            ->latest('id')
            ->paginate(20);

        return view('sales.index', compact('sales'));
    }

    public function create(): View
    {
        return $this->form(new Sale);
    }

    public function store(SaleRequest $request, DocumentNumberService $numbers, AuditService $audit): RedirectResponse
    {
        $data = $request->validated();
        $sale = DB::transaction(function () use ($request, $data, $numbers, $audit): Sale {
            $sale = Sale::create([
                'number' => $numbers->next('INV'),
                'status' => 'draft',
                'created_by' => $request->user()->id,
                ...$this->headerValues($data),
            ]);
            $sale->update(['total' => $this->replaceItems($sale, $data['items'])]);
            $audit->record($request, 'CREATE', 'Sale', $sale, newValues: $sale->load('items')->toArray());

            return $sale;
        });

        return redirect()->route('sales.index')->with('success', "{$sale->number} disimpan sebagai draft.");
    }

    public function edit(Sale $sale): View
    {
        $this->ensureEditable($sale);
        $sale->load('items');

        return $this->form($sale);
    }

    public function update(SaleRequest $request, Sale $sale, AuditService $audit): RedirectResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($request, $sale, $data, $audit): void {
            $sale = Sale::query()->whereKey($sale->getKey())->lockForUpdate()->firstOrFail();
            $this->ensureEditable($sale);
            $old = $sale->load('items')->toArray();
            $sale->update($this->headerValues($data));
            $sale->update(['total' => $this->replaceItems($sale, $data['items'])]);
            $audit->record(
                $request,
                'UPDATE',
                'Sale',
                $sale,
                $old,
                $sale->fresh('items')->toArray(),
                "Memperbarui draft penjualan {$sale->number}.",
            );
        });

        return redirect()->route('sales.index')->with('success', "Draft {$sale->number} berhasil diperbarui.");
    }

    public function post(Request $request, Sale $sale, SalesService $salesService, AuditService $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $sale, $salesService, $audit): void {
            $old = $sale->toArray();
            $posted = $salesService->post($sale, $request->user());
            $audit->record($request, 'POST', 'Sale', $posted, $old, $posted->toArray());
        });

        $sale->load(['customer:id,name', 'warehouse:id,name']);
        $warehouseUsers = User::query()->where('role', UserRole::Warehouse)->where('is_active', true)->get();
        Notification::send($warehouseUsers, new SaleAwaitingDelivery($sale->id, $sale->number, $sale->customer->name, $sale->warehouse->name));

        return back()->with('success', 'Penjualan diposting dan siap diproses gudang. Stok berkurang saat surat jalan diposting.');
    }

    public function cancel(Request $request, Sale $sale, AuditService $audit): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']], ['reason.required' => 'Alasan pembatalan wajib diisi.']);

        DB::transaction(function () use ($request, $sale, $data, $audit): void {
            $sale = Sale::query()->whereKey($sale->getKey())->lockForUpdate()->firstOrFail();
            $this->ensureEditable($sale);
            $old = $sale->toArray();
            $sale->update([
                'status' => 'cancelled',
                'cancelled_by' => $request->user()->id,
                'cancelled_at' => now(),
                'cancellation_reason' => $data['reason'],
            ]);
            $audit->record(
                $request,
                'CANCEL',
                'Sale',
                $sale,
                $old,
                $sale->fresh()->toArray(),
                "Membatalkan draft penjualan {$sale->number}. Alasan: {$data['reason']}.",
            );
        });

        return back()->with('success', "Penjualan {$sale->number} dibatalkan.");
    }

    private function form(Sale $sale): View
    {
        return view('sales.create', [
            'sale' => $sale,
            'customers' => Customer::query()->where('status', 'active')->orderBy('name')->get(),
            'products' => Product::query()->where('type', ProductType::FinishedGood)->where('is_active', true)->with('unit')->orderBy('name')->get(),
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(),
            'cashAccounts' => CashAccount::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    private function ensureEditable(Sale $sale): void
    {
        if ($sale->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Hanya penjualan draft yang dapat diedit atau dibatalkan. Penjualan terposting dikoreksi melalui retur.']);
        }
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function headerValues(array $data): array
    {
        $cashAccount = null;
        if ($data['payment_type'] === 'cash') {
            $accountType = $data['payment_method'] === 'transfer' ? 'bank' : 'cash';
            $cashAccount = CashAccount::query()
                ->where('type', $accountType)
                ->where('is_active', true)
                ->orderBy('id')
                ->first();

            if ($cashAccount === null) {
                throw ValidationException::withMessages([
                    'payment_method' => $accountType === 'bank'
                        ? 'Akun bank aktif belum tersedia.'
                        : 'Akun kas aktif belum tersedia.',
                ]);
            }
        }

        return [
            'customer_id' => $data['customer_id'],
            'warehouse_id' => $data['warehouse_id'],
            'sale_date' => $data['sale_date'],
            'payment_type' => $data['payment_type'],
            'payment_method' => $data['payment_method'] ?? null,
            'sender_bank' => $data['sender_bank'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'cash_account_id' => $cashAccount?->id,
            'notes' => $data['notes'] ?? null,
        ];
    }

    /** @param array<int, array{product_id: mixed, quantity: mixed, unit_price: mixed}> $items */
    private function replaceItems(Sale $sale, array $items): string
    {
        $sale->items()->delete();
        $total = '0.00';

        foreach ($items as $item) {
            $lineTotal = bcmul((string) $item['quantity'], (string) $item['unit_price'], 2);
            $sale->items()->create([
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'line_total' => $lineTotal,
            ]);
            $total = bcadd($total, $lineTotal, 2);
        }

        return $total;
    }
}
