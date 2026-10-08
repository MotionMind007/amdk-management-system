<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductionRequest;
use App\Models\DailyProduction;
use App\Models\Product;
use App\Models\Warehouse;
use App\ProductType;
use App\Services\AuditService;
use App\Services\DocumentNumberService;
use App\Services\ProductionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductionController extends Controller
{
    public function index(): View
    {
        $productions = DailyProduction::query()
            ->with(['warehouse:id,name', 'items.product:id,name', 'items.product.compositions:id,product_id'])
            ->latest('production_date')
            ->latest('id')
            ->paginate(20);

        $finishedProducts = Product::query()
            ->where('type', ProductType::FinishedGood)
            ->where('is_active', true)
            ->withCount('compositions')
            ->orderBy('name')
            ->get();

        return view('production.index', compact('productions', 'finishedProducts'));
    }

    public function create(): View
    {
        return view('production.create', [
            'production' => new DailyProduction,
            'products' => Product::query()->where('type', ProductType::FinishedGood)->where('is_active', true)->orderBy('name')->get(),
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(ProductionRequest $request, DocumentNumberService $numberService, AuditService $auditService): RedirectResponse
    {
        $validated = $request->validated();

        $production = DB::transaction(function () use ($request, $validated, $numberService, $auditService): DailyProduction {
            $production = DailyProduction::create(['number' => $numberService->next('PROD'), 'production_date' => $validated['production_date'], 'warehouse_id' => $validated['warehouse_id'], 'status' => 'draft', 'notes' => $validated['notes'] ?? null, 'created_by' => $request->user()->id]);
            foreach ($validated['items'] as $item) {
                $production->items()->create($item);
            }
            $auditService->record($request, 'CREATE', 'Production', $production, newValues: $production->load('items')->toArray());

            return $production;
        });

        return redirect()->route('production.index')->with('success', "Rekap {$production->number} disimpan sebagai draft.");
    }

    public function edit(DailyProduction $production): View
    {
        abort_unless($production->status === 'draft', 404);

        $production->load('items');

        return view('production.create', [
            'production' => $production,
            'products' => Product::query()->where('type', ProductType::FinishedGood)->where('is_active', true)->orderBy('name')->get(),
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(ProductionRequest $request, DailyProduction $production, AuditService $auditService): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($request, $production, $validated, $auditService): void {
            $production = DailyProduction::query()->whereKey($production->getKey())->lockForUpdate()->firstOrFail();

            if ($production->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Hanya rekap produksi draft yang dapat diedit.']);
            }

            $oldValues = $production->load('items')->toArray();
            $production->update([
                'production_date' => $validated['production_date'],
                'warehouse_id' => $validated['warehouse_id'],
                'notes' => $validated['notes'] ?? null,
            ]);
            $production->items()->delete();

            foreach ($validated['items'] as $item) {
                $production->items()->create($item);
            }

            $auditService->record($request, 'UPDATE', 'Production', $production, $oldValues, $production->load('items')->toArray());
        });

        return redirect()->route('production.index')->with('success', "Rekap {$production->number} berhasil diperbarui.");
    }

    public function post(Request $request, DailyProduction $production, ProductionService $productionService, AuditService $auditService): RedirectResponse
    {
        DB::transaction(function () use ($request, $production, $productionService, $auditService): void {
            $oldValues = $production->toArray();
            $posted = $productionService->post($production, $request->user());
            $auditService->record($request, 'POST', 'Production', $posted, $oldValues, $posted->toArray());
        });

        return back()->with('success', 'Produksi berhasil diposting dan stok telah diperbarui.');
    }
}
