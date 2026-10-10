<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('purchasing.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', Rule::exists('suppliers', 'id')->where('status', 'active')->whereNull('deleted_at')],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'order_date' => ['required', 'date'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'payment_type' => ['required', Rule::in(['cash', 'credit'])],
            'due_date' => ['exclude_unless:payment_type,credit', 'required', 'date', 'after_or_equal:order_date'],
            'cash_account_id' => [
                'exclude_unless:payment_type,cash',
                'required',
                Rule::exists('cash_accounts', 'id')->where('is_active', true),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                'distinct',
                Rule::exists('products', 'id')->where('is_active', true)->whereNull('deleted_at'),
            ],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'payment_type.required' => 'Jenis pembayaran wajib dipilih.',
            'due_date.required' => 'Tanggal jatuh tempo wajib diisi untuk pembelian kredit.',
            'due_date.after_or_equal' => 'Tanggal jatuh tempo tidak boleh sebelum tanggal pembelian.',
            'cash_account_id.required' => 'Akun kas atau bank wajib dipilih untuk pembelian cash.',
            'cash_account_id.exists' => 'Akun kas atau bank yang dipilih tidak tersedia.',
            'supplier_id.exists' => 'Supplier yang dipilih tidak aktif atau tidak tersedia.',
            'warehouse_id.exists' => 'Gudang yang dipilih tidak aktif atau tidak tersedia.',
            'items.*.product_id.exists' => 'Produk atau bahan yang dipilih tidak aktif atau tidak tersedia.',
        ];
    }
}
