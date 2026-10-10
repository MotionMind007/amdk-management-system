<?php

namespace App\Http\Controllers;

use App\Http\Requests\StockOpnameRequest;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockOpname;
use App\Models\Warehouse;
use App\Services\AuditService;
use App\Services\DocumentNumberService;
use App\Services\StockOpnameService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StockOpnameController extends Controller
{
    public function index(): View
    {
        $stockOpnames = StockOpname::query()
            ->with([
                'warehouse:id,name',
                'creator:id,name',
                'poster:id,name',
                'items:id,stock_opname_id,system_quantity,physical_quantity',
            ])
            ->latest('opname_date')
            ->latest('id')
            ->paginate(20);

        return view('inventory.opnames.index', compact('stockOpnames'));
    }

    public function create(): View
    {
        return $this->form(new StockOpname);
    }

    public function store(
        StockOpnameRequest $request,
        DocumentNumberService $numberService,
        AuditService $auditService,
    ): RedirectResponse {
        $validated = $request->validated();

        $stockOpname = DB::transaction(function () use ($request, $validated, $numberService, $auditService): StockOpname {
            $productIds = collect($validated['items'])->pluck('product_id')->map(fn ($id): int => (int) $id);
            $balances = StockBalance::query()
                ->where('warehouse_id', $validated['warehouse_id'])
                ->whereIn('product_id', $productIds)
                ->lockForUpdate()
                ->pluck('quantity', 'product_id');

            $stockOpname = StockOpname::create([
                'number' => $numberService->next('OPN', (int) substr($validated['period'], 0, 4)),
                'period' => $validated['period'],
                'opname_date' => $validated['opname_date'],
                'warehouse_id' => $validated['warehouse_id'],
                'status' => 'draft',
                'notes' => $validated['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            foreach ($validated['items'] as $item) {
                $stockOpname->items()->create([
                    'product_id' => $item['product_id'],
                    'system_quantity' => $balances->get((int) $item['product_id'], 0),
                    'physical_quantity' => $item['physical_quantity'],
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            $stockOpname->load(['items.product.unit', 'warehouse']);
            $auditService->record(
                $request,
                'CREATE',
                'Stock Opname',
                $stockOpname,
                newValues: $stockOpname->toArray(),
                description: $this->auditDescription($stockOpname, 'dibuat sebagai draft'),
            );

            return $stockOpname;
        });

        return redirect()
            ->route('inventory.opnames.show', $stockOpname)
            ->with('success', "Stok opname {$stockOpname->number} berhasil disimpan sebagai draft.");
    }

    public function show(StockOpname $stockOpname): View
    {
        $stockOpname->load(['items.product.unit', 'warehouse', 'creator', 'poster']);

        return view('inventory.opnames.show', compact('stockOpname'));
    }

    public function edit(StockOpname $stockOpname): View
    {
        abort_unless($stockOpname->status === 'draft', 404);
        $stockOpname->load(['items.product.unit', 'warehouse']);

        return $this->form($stockOpname);
    }

    public function update(
        StockOpnameRequest $request,
        StockOpname $stockOpname,
        AuditService $auditService,
    ): RedirectResponse {
        $validated = $request->validated();

        DB::transaction(function () use ($request, $stockOpname, $validated, $auditService): void {
            $stockOpname = StockOpname::query()
                ->whereKey($stockOpname->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($stockOpname->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Hanya stok opname draft yang dapat diedit.']);
            }

            if ($stockOpname->period !== $validated['period'] || $stockOpname->warehouse_id !== (int) $validated['warehouse_id']) {
                throw ValidationException::withMessages([
                    'period' => 'Periode dan gudang tidak dapat diubah setelah draft dibuat.',
                ]);
            }

            $stockOpname->load('items');
            $submittedItems = collect($validated['items'])->keyBy(fn (array $item): int => (int) $item['product_id']);
            $existingProductIds = $stockOpname->items->pluck('product_id')->sort()->values();
            $submittedProductIds = $submittedItems->keys()->sort()->values();

            if ($existingProductIds->all() !== $submittedProductIds->all()) {
                throw ValidationException::withMessages([
                    'items' => 'Daftar produk pada draft stok opname tidak dapat diubah.',
                ]);
            }

            $oldValues = $stockOpname->toArray();
            $stockOpname->update([
                'opname_date' => $validated['opname_date'],
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($stockOpname->items as $item) {
                $submittedItem = $submittedItems->get($item->product_id);
                $item->update([
                    'physical_quantity' => $submittedItem['physical_quantity'],
                    'notes' => $submittedItem['notes'] ?? null,
                ]);
            }

            $stockOpname->load(['items.product.unit', 'warehouse']);
            $auditService->record(
                $request,
                'UPDATE',
                'Stock Opname',
                $stockOpname,
                $oldValues,
                $stockOpname->toArray(),
                $this->auditDescription($stockOpname, 'diperbarui'),
            );
        });

        return redirect()
            ->route('inventory.opnames.show', $stockOpname)
            ->with('success', "Draft {$stockOpname->number} berhasil diperbarui.");
    }

    public function post(
        Request $request,
        StockOpname $stockOpname,
        StockOpnameService $stockOpnameService,
        AuditService $auditService,
    ): RedirectResponse {
        DB::transaction(function () use ($request, $stockOpname, $stockOpnameService, $auditService): void {
            $oldValues = $stockOpname->load(['items.product.unit', 'warehouse'])->toArray();
            $posted = $stockOpnameService->post($stockOpname, $request->user());
            $auditService->record(
                $request,
                'POST',
                'Stock Opname',
                $posted,
                $oldValues,
                $posted->toArray(),
                $this->auditDescription($posted, 'diposting dan saldo stok disesuaikan'),
            );
        });

        return redirect()
            ->route('inventory.opnames.show', $stockOpname)
            ->with('success', 'Stok opname berhasil diposting dan saldo stok telah disesuaikan.');
    }

    private function form(StockOpname $stockOpname): View
    {
        $warehouses = Warehouse::query()->where('is_active', true)->orderBy('name')->get();

        if ($stockOpname->exists) {
            $products = $stockOpname->items->pluck('product')->filter()->values();
            $balanceMap = [];
        } else {
            $products = Product::query()
                ->where('is_active', true)
                ->with(['unit:id,code', 'stockBalances:id,product_id,warehouse_id,quantity'])
                ->orderBy('name')
                ->get();
            $balanceMap = $products->mapWithKeys(fn (Product $product): array => [
                $product->id => $product->stockBalances
                    ->mapWithKeys(fn (StockBalance $balance): array => [$balance->warehouse_id => (float) $balance->quantity])
                    ->all(),
            ])->all();
        }

        return view('inventory.opnames.form', [
            'stockOpname' => $stockOpname,
            'products' => $products,
            'warehouses' => $warehouses,
            'defaultWarehouseId' => $stockOpname->exists
                ? $stockOpname->warehouse_id
                : ($warehouses->count() === 1 ? $warehouses->first()->id : null),
            'balanceMap' => $balanceMap,
        ]);
    }

    private function auditDescription(StockOpname $stockOpname, string $action): string
    {
        $stockOpname->loadMissing(['items.product.unit', 'warehouse']);
        $differences = $stockOpname->items
            ->filter(fn ($item): bool => $item->difference() !== 0.0)
            ->map(function ($item): string {
                $difference = $item->difference();
                $sign = $difference > 0 ? '+' : '';
                $unit = $item->product->unit->code;

                return "{$item->product->name}: sistem ".number_format((float) $item->system_quantity, 3, ',', '.')
                    ." {$unit}, fisik ".number_format((float) $item->physical_quantity, 3, ',', '.')
                    .", selisih {$sign}".number_format($difference, 3, ',', '.')." {$unit}";
            });
        $period = Carbon::createFromFormat('Y-m', $stockOpname->period)->translatedFormat('F Y');
        $description = "Stok opname {$stockOpname->number} periode {$period} di {$stockOpname->warehouse->name} {$action}. ";

        if ($differences->isEmpty()) {
            return $description.'Tidak ada selisih antara stok sistem dan stok fisik.';
        }

        return $description.$differences->count().' dari '.$stockOpname->items->count().' barang memiliki selisih. '.$differences->implode('; ').'.';
    }
}
