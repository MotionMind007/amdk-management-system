<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\OpeningStockRequest;
use App\Models\OpeningStock;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\AuditService;
use App\Services\DocumentNumberService;
use App\Services\OpeningStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OpeningStockController extends Controller
{
    public function index(): View
    {
        $openingStocks = OpeningStock::query()
            ->with(['warehouse:id,name', 'creator:id,name', 'items.product:id,name'])
            ->latest('stock_date')
            ->latest('id')
            ->paginate(20);

        return view('admin.opening-stocks.index', compact('openingStocks'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return $this->form(new OpeningStock);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(OpeningStockRequest $request, DocumentNumberService $numberService, AuditService $auditService): RedirectResponse
    {
        $validated = $request->validated();

        $openingStock = DB::transaction(function () use ($request, $validated, $numberService, $auditService): OpeningStock {
            $openingStock = OpeningStock::create([
                'number' => $numberService->next('OPEN', (int) date('Y', strtotime($validated['stock_date']))),
                'stock_date' => $validated['stock_date'],
                'warehouse_id' => $validated['warehouse_id'],
                'status' => 'draft',
                'notes' => $validated['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            foreach ($validated['items'] as $item) {
                $openingStock->items()->create($item);
            }

            $auditService->record($request, 'CREATE', 'Opening Stock', $openingStock, newValues: $openingStock->load('items')->toArray());

            return $openingStock;
        });

        return redirect()->route('admin.opening-stocks.index')->with('success', "Stok awal {$openingStock->number} disimpan sebagai draft.");
    }

    /**
     * Display the specified resource.
     */
    public function edit(OpeningStock $openingStock): View
    {
        abort_unless($openingStock->status === 'draft', 404);
        $openingStock->load('items');

        return $this->form($openingStock);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(OpeningStockRequest $request, OpeningStock $openingStock, AuditService $auditService): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($request, $openingStock, $validated, $auditService): void {
            $openingStock = OpeningStock::query()->whereKey($openingStock->getKey())->lockForUpdate()->firstOrFail();

            if ($openingStock->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Hanya stok awal draft yang dapat diedit.']);
            }

            $oldValues = $openingStock->load('items')->toArray();
            $openingStock->update([
                'stock_date' => $validated['stock_date'],
                'warehouse_id' => $validated['warehouse_id'],
                'notes' => $validated['notes'] ?? null,
            ]);
            $openingStock->items()->delete();

            foreach ($validated['items'] as $item) {
                $openingStock->items()->create($item);
            }

            $auditService->record($request, 'UPDATE', 'Opening Stock', $openingStock, $oldValues, $openingStock->load('items')->toArray());
        });

        return redirect()->route('admin.opening-stocks.index')->with('success', "Draft {$openingStock->number} berhasil diperbarui.");
    }

    /**
     * Remove the specified resource from storage.
     */
    public function post(Request $request, OpeningStock $openingStock, OpeningStockService $openingStockService, AuditService $auditService): RedirectResponse
    {
        DB::transaction(function () use ($request, $openingStock, $openingStockService, $auditService): void {
            $oldValues = $openingStock->load('items')->toArray();
            $posted = $openingStockService->post($openingStock, $request->user());
            $auditService->record($request, 'POST', 'Opening Stock', $posted, $oldValues, $posted->toArray());
        });

        return back()->with('success', 'Stok awal berhasil diposting dan saldo stok telah diperbarui.');
    }

    private function form(OpeningStock $openingStock): View
    {
        return view('admin.opening-stocks.form', [
            'openingStock' => $openingStock,
            'products' => Product::query()->where('is_active', true)->with('unit:id,code')->orderBy('name')->get(),
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}
