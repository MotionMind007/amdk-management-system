<?php

namespace App\Http\Requests;

use App\Models\DailyProduction;
use App\Models\Product;
use App\ProductionType;
use App\ProductType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('production.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'production_type' => ['required', Rule::enum(ProductionType::class)],
            'production_date' => ['required', 'date'],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'distinct', Rule::exists('products', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.rejected_quantity' => ['nullable', 'numeric', 'min:0'],
            'materials' => ['exclude_unless:production_type,packaging', 'required', 'array', 'min:1'],
            'materials.*.product_id' => ['exclude_unless:production_type,packaging', 'required', 'distinct', Rule::exists('products', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'materials.*.quantity' => ['exclude_unless:production_type,packaging', 'required', 'numeric', 'gt:0'],
        ];
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $productionType = ProductionType::tryFrom((string) $this->input('production_type'));
                $outputIds = collect($this->input('items', []))->pluck('product_id')->filter()->map(fn ($id): int => (int) $id);
                $materialIds = collect($this->input('materials', []))->pluck('product_id')->filter()->map(fn ($id): int => (int) $id);

                if ($productionType === null || $outputIds->isEmpty()) {
                    return;
                }

                $outputs = Product::query()->whereKey($outputIds)->get();

                if ($productionType === ProductionType::FinishedGood && $outputs->contains(fn (Product $product): bool => $product->type !== ProductType::FinishedGood)) {
                    $validator->errors()->add('items', 'Produksi produk jadi hanya dapat menghasilkan produk jadi.');
                }

                if ($productionType === ProductionType::Packaging && $outputs->contains(fn (Product $product): bool => ! in_array($product->type, [ProductType::PackagingMaterial, ProductType::SemiFinished], true))) {
                    $validator->errors()->add('items', 'Produksi kemasan hanya dapat menghasilkan bahan kemasan atau barang setengah jadi.');
                }

                if ($productionType === ProductionType::Packaging && $outputIds->intersect($materialIds)->isNotEmpty()) {
                    $validator->errors()->add('materials', 'Produk hasil tidak boleh digunakan sebagai bahan pada rekap yang sama.');
                }

                if ($productionType === ProductionType::Packaging) {
                    $materials = Product::query()->whereKey($materialIds)->get();

                    if ($materials->contains(fn (Product $product): bool => $product->type === ProductType::FinishedGood)) {
                        $validator->errors()->add('materials', 'Produk jadi tidak dapat digunakan sebagai bahan produksi kemasan.');
                    }
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('production_type')) {
            return;
        }

        $production = $this->route('production');

        $this->merge([
            'production_type' => $production instanceof DailyProduction
                ? $production->production_type->value
                : ProductionType::FinishedGood->value,
        ]);
    }

    public function messages(): array
    {
        return [
            'production_type.required' => 'Jenis produksi wajib dipilih.',
            'production_type.enum' => 'Jenis produksi yang dipilih tidak tersedia.',
            'production_date.required' => 'Tanggal produksi wajib diisi.',
            'warehouse_id.required' => 'Gudang wajib dipilih.',
            'warehouse_id.exists' => 'Gudang yang dipilih tidak tersedia.',
            'items.required' => 'Minimal satu hasil produksi wajib diisi.',
            'items.min' => 'Minimal satu hasil produksi wajib diisi.',
            'items.*.product_id.required' => 'Produk hasil wajib dipilih.',
            'items.*.product_id.distinct' => 'Produk hasil tidak boleh sama.',
            'items.*.product_id.exists' => 'Produk hasil yang dipilih tidak tersedia.',
            'items.*.quantity.required' => 'Jumlah hasil produksi wajib diisi.',
            'items.*.quantity.numeric' => 'Jumlah hasil produksi harus berupa angka.',
            'items.*.quantity.gt' => 'Jumlah hasil produksi harus lebih besar dari nol.',
            'items.*.rejected_quantity.numeric' => 'Jumlah reject harus berupa angka.',
            'items.*.rejected_quantity.min' => 'Jumlah reject tidak boleh kurang dari nol.',
            'materials.required' => 'Minimal satu bahan aktual wajib diisi untuk produksi kemasan.',
            'materials.min' => 'Minimal satu bahan aktual wajib diisi untuk produksi kemasan.',
            'materials.*.product_id.required' => 'Bahan aktual wajib dipilih.',
            'materials.*.product_id.distinct' => 'Bahan aktual tidak boleh sama.',
            'materials.*.product_id.exists' => 'Bahan aktual yang dipilih tidak tersedia.',
            'materials.*.quantity.required' => 'Jumlah bahan aktual wajib diisi.',
            'materials.*.quantity.numeric' => 'Jumlah bahan aktual harus berupa angka.',
            'materials.*.quantity.gt' => 'Jumlah bahan aktual harus lebih besar dari nol.',
        ];
    }
}
