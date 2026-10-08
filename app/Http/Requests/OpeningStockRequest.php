<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OpeningStockRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('system.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'stock_date' => ['required', 'date', 'before_or_equal:today'],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'distinct:strict', Rule::exists('products', 'id')->where('is_active', true)],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'stock_date.required' => 'Tanggal stok awal wajib diisi.',
            'stock_date.before_or_equal' => 'Tanggal stok awal tidak boleh melewati hari ini.',
            'warehouse_id.required' => 'Gudang wajib dipilih.',
            'warehouse_id.exists' => 'Gudang yang dipilih tidak tersedia.',
            'items.required' => 'Minimal satu barang wajib diisi.',
            'items.min' => 'Minimal satu barang wajib diisi.',
            'items.*.product_id.required' => 'Produk atau bahan wajib dipilih.',
            'items.*.product_id.distinct' => 'Produk atau bahan tidak boleh sama.',
            'items.*.product_id.exists' => 'Produk atau bahan yang dipilih tidak tersedia.',
            'items.*.quantity.required' => 'Jumlah stok wajib diisi.',
            'items.*.quantity.numeric' => 'Jumlah stok harus berupa angka.',
            'items.*.quantity.gt' => 'Jumlah stok harus lebih besar dari nol.',
        ];
    }
}
