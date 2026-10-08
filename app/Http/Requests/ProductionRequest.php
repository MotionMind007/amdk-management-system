<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
            'production_date' => ['required', 'date'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.rejected_quantity' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
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
        ];
    }
}
