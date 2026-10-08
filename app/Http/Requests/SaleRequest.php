<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('sales.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'sale_date' => ['required', 'date'],
            'payment_type' => ['required', Rule::in(['cash', 'credit'])],
            'due_date' => ['exclude_unless:payment_type,credit', 'required', 'date', 'after_or_equal:sale_date'],
            'cash_account_id' => [
                'exclude_unless:payment_type,cash',
                'required',
                Rule::exists('cash_accounts', 'id')->where('is_active', true),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'payment_type.required' => 'Jenis pembayaran wajib dipilih.',
            'due_date.required' => 'Tanggal jatuh tempo wajib diisi untuk penjualan kredit.',
            'due_date.after_or_equal' => 'Tanggal jatuh tempo tidak boleh sebelum tanggal penjualan.',
            'cash_account_id.required' => 'Akun kas atau bank wajib dipilih untuk penjualan cash.',
            'cash_account_id.exists' => 'Akun kas atau bank yang dipilih tidak tersedia.',
        ];
    }
}
