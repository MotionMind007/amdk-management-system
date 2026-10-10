<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryAdjustmentRequest;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\AuditService;
use App\Services\InventoryService;
use App\StockMovementType;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class InventoryAdjustmentController extends Controller
{
    public function create(): View
    {
        return view('inventory.adjustment', [
            'products' => Product::query()->where('is_active', true)->with('unit:id,code')->orderBy('name')->get(),
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreInventoryAdjustmentRequest $request, InventoryService $inventory, AuditService $audit): RedirectResponse
    {
        $data = $request->validated();
        $product = Product::query()->with('unit:id,code')->findOrFail($data['product_id']);
        $warehouse = Warehouse::findOrFail($data['warehouse_id']);
        $method = $data['direction'] === 'in' ? 'increase' : 'decrease';
        $movement = $inventory->{$method}(
            $product,
            $warehouse,
            (string) $data['quantity'],
            StockMovementType::Adjustment,
            $request->user(),
            notes: $data['notes'],
        );
        $directionLabel = $data['direction'] === 'in' ? 'Penambahan' : 'Pengurangan';
        $auditDescription = "{$directionLabel} stok {$product->name} sebanyak ".number_format((float) $data['quantity'], 3, ',', '.')." {$product->unit->code} di {$warehouse->name}.";
        $auditDescription .= " Alasan: {$data['notes']}. Saldo ".number_format((float) $movement->balance_before, 3, ',', '.').' menjadi '.number_format((float) $movement->balance_after, 3, ',', '.')." {$product->unit->code}.";

        $audit->record($request, 'ADJUST', 'Inventory', $movement, newValues: $movement->toArray(), description: $auditDescription);

        return redirect()->route('inventory.index')->with('success', 'Penyesuaian stok berhasil dicatat.');
    }
}
