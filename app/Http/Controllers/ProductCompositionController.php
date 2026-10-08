<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\ProductType;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductCompositionController extends Controller
{
    public function edit(Product $product): View
    {
        abort_unless($product->type === ProductType::FinishedGood, 404);
        $product->load('unit', 'compositions.component.unit');
        $components = Product::query()->where('id', '!=', $product->id)->where('is_active', true)->with('unit')->orderBy('name')->get();

        return view('production.composition', compact('product', 'components'));
    }

    public function update(Request $request, Product $product, AuditService $auditService): RedirectResponse
    {
        $request->merge(['components' => collect($request->input('components', []))->filter(fn (array $component): bool => filled($component['product_id'] ?? null) || filled($component['quantity'] ?? null))->values()->all()]);
        $validated = $request->validate([
            'components' => ['required', 'array', 'min:1'],
            'components.*.product_id' => ['required', 'distinct', 'exists:products,id'],
            'components.*.quantity' => ['required', 'numeric', 'gt:0'],
        ], [
            'components.required' => 'Tambahkan minimal satu bahan.',
            'components.min' => 'Tambahkan minimal satu bahan.',
            'components.*.product_id.required' => 'Bahan wajib dipilih.',
            'components.*.product_id.distinct' => 'Bahan yang sama tidak boleh ditambahkan lebih dari satu kali.',
            'components.*.product_id.exists' => 'Bahan yang dipilih tidak tersedia.',
            'components.*.quantity.required' => 'Jumlah bahan wajib diisi.',
            'components.*.quantity.numeric' => 'Jumlah bahan harus berupa angka.',
            'components.*.quantity.gt' => 'Jumlah bahan harus lebih besar dari nol.',
        ]);
        DB::transaction(function () use ($request, $product, $validated, $auditService): void {
            $oldValues = $product->compositions()->get()->toArray();
            $product->compositions()->delete();
            foreach ($validated['components'] as $component) {
                $product->compositions()->create(['component_product_id' => $component['product_id'], 'quantity' => $component['quantity']]);
            }
            $auditService->record($request, 'UPDATE', 'ProductComposition', $product, $oldValues, $product->compositions()->get()->toArray());
        });

        return redirect()->route('production.index')->with('success', 'Komposisi produk berhasil disimpan.');
    }
}
