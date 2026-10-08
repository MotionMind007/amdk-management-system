<?php

namespace App\Http\Controllers;

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
            'products' => Product::query()->where('type', ProductType::FinishedGood)->where('is_active', true)->orderBy('name')->get(),
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, DocumentNumberService $numberService, AuditService $auditService): RedirectResponse
    {
        $validated = $request->validate([
            'production_date' => ['required', 'date'], 'warehouse_id' => ['required', 'exists:warehouses,id'], 'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'], 'items.*.product_id' => ['required', 'distinct', 'exists:products,id'], 'items.*.quantity' => ['required', 'numeric', 'gt:0'], 'items.*.rejected_quantity' => ['nullable', 'numeric', 'min:0'],
        ]);

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
