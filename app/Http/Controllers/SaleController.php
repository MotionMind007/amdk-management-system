<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaleRequest;
use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Warehouse;
use App\ProductType;
use App\Services\AuditService;
use App\Services\DocumentNumberService;
use App\Services\SalesService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SaleController extends Controller
{
    public function index(): View
    {
        $sales = Sale::query()->with('customer:id,name')->latest('sale_date')->latest('id')->paginate(20);

        return view('sales.index', compact('sales'));
    }

    public function create(): View
    {
        return view('sales.create', [
            'customers' => Customer::query()->where('status', 'active')->orderBy('name')->get(),
            'products' => Product::query()->where('type', ProductType::FinishedGood)->where('is_active', true)->with('unit')->orderBy('name')->get(),
            'warehouses' => Warehouse::query()->where('is_active', true)->get(),
            'cashAccounts' => CashAccount::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(SaleRequest $request, DocumentNumberService $numbers, AuditService $audit): RedirectResponse
    {
        $data = $request->validated();
        $sale = DB::transaction(function () use ($request, $data, $numbers, $audit): Sale {
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

            $sale = Sale::create([
                'number' => $numbers->next('INV'),
                'customer_id' => $data['customer_id'],
                'warehouse_id' => $data['warehouse_id'],
                'sale_date' => $data['sale_date'],
                'payment_type' => $data['payment_type'],
                'payment_method' => $data['payment_method'] ?? null,
                'sender_bank' => $data['sender_bank'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'cash_account_id' => $cashAccount?->id,
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);
            foreach ($data['items'] as $item) {
                $sale->items()->create(['product_id' => $item['product_id'], 'quantity' => $item['quantity'], 'unit_price' => $item['unit_price'], 'line_total' => bcmul($item['quantity'], $item['unit_price'], 2)]);
            }
            $audit->record($request, 'CREATE', 'Sale', $sale, newValues: $sale->load('items')->toArray());

            return $sale;
        });

        return redirect()->route('sales.index')->with('success', "{$sale->number} disimpan sebagai draft.");
    }

    public function post(Request $request, Sale $sale, SalesService $salesService, AuditService $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $sale, $salesService, $audit): void {
            $old = $sale->toArray();
            $posted = $salesService->post($sale, $request->user());
            $audit->record($request, 'POST', 'Sale', $posted, $old, $posted->toArray());
        });

        return back()->with('success', 'Penjualan diposting, stok dan pembayaran telah diperbarui.');
    }
}
