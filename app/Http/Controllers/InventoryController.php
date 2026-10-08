<?php

namespace App\Http\Controllers;

use App\Models\StockBalance;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->toString();
        $balances = StockBalance::query()
            ->with(['product.unit:id,code,name', 'warehouse:id,code,name'])
            ->when($search !== '', fn ($query) => $query->whereHas('product', function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            }))
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $lowStockCount = StockBalance::query()
            ->join('products', 'products.id', '=', 'stock_balances.product_id')
            ->whereColumn('stock_balances.quantity', '<', 'products.minimum_stock')
            ->where('products.is_active', true)
            ->count();

        return view('inventory.index', compact('balances', 'search', 'lowStockCount'));
    }

    public function movements(Request $request): View
    {
        $movements = StockMovement::query()
            ->with(['product:id,sku,name,unit_id', 'product.unit:id,code', 'warehouse:id,code,name', 'user:id,name'])
            ->latest('occurred_at')
            ->latest('id')
            ->paginate(25);

        return view('inventory.movements', compact('movements'));
    }
}
