<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\Product;
use App\Models\Unit;
use App\ProductType;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->toString();
        $products = Product::query()
            ->with('unit:id,code,name')
            ->where('is_active', true)
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('products.index', compact('products', 'search'));
    }

    public function create(): View
    {
        return view('products.form', [
            'product' => new Product,
            'units' => Unit::query()->orderBy('name')->get(),
            'types' => ProductType::cases(),
        ]);
    }

    public function store(ProductRequest $request, AuditService $auditService): RedirectResponse
    {
        DB::transaction(function () use ($request, $auditService): void {
            $product = Product::create($request->validated());
            $auditService->record($request, 'CREATE', 'Product', $product, newValues: $product->toArray());
        });

        return redirect()->route('products.index')->with('success', 'Produk berhasil ditambahkan.');
    }

    public function edit(Product $product): View
    {
        return view('products.form', [
            'product' => $product,
            'units' => Unit::query()->orderBy('name')->get(),
            'types' => ProductType::cases(),
        ]);
    }

    public function update(ProductRequest $request, Product $product, AuditService $auditService): RedirectResponse
    {
        DB::transaction(function () use ($request, $product, $auditService): void {
            $oldValues = $product->toArray();
            $product->update($request->validated());
            $auditService->record($request, 'UPDATE', 'Product', $product, $oldValues, $product->fresh()->toArray());
        });

        return redirect()->route('products.index')->with('success', 'Produk berhasil diperbarui.');
    }

    public function destroy(Request $request, Product $product, AuditService $auditService): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('inventory.manage'), 403);

        DB::transaction(function () use ($request, $product, $auditService): void {
            $oldValues = $product->toArray();
            $product->update(['is_active' => false]);
            $auditService->record($request, 'DELETE', 'Product', $product, $oldValues, $product->fresh()->toArray());
        });

        return back()->with('success', 'Produk berhasil dihapus dari master. Histori transaksi tetap tersimpan.');
    }
}
