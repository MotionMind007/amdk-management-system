<?php

namespace App\Http\Requests;

use App\ProductType;
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
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('status', 'active')->whereNull('deleted_at')],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'sale_date' => ['required', 'date'],
            'payment_type' => ['required', Rule::in(['cash', 'credit'])],
            'due_date' => ['exclude_unless:payment_type,credit', 'required', 'date', 'after_or_equal:sale_date'],
            'payment_method' => [
                'exclude_unless:payment_type,cash',
                'required',
                Rule::in(['cash', 'transfer']),
            ],
            'sender_bank' => [
                Rule::excludeIf(fn (): bool => $this->input('payment_type') !== 'cash' || $this->input('payment_method') !== 'transfer'),
                'required',
                'string',
                'max:100',
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                'distinct',
                Rule::exists('products', 'id')
                    ->where('is_active', true)
                    ->where('type', ProductType::FinishedGood->value)
                    ->whereNull('deleted_at'),
            ],
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
            'payment_method.required' => 'Metode pembayaran wajib dipilih untuk penjualan langsung.',
            'payment_method.in' => 'Metode pembayaran yang dipilih tidak tersedia.',
            'sender_bank.required' => 'Nama bank pengirim wajib diisi untuk pembayaran transfer.',
            'sender_bank.max' => 'Nama bank pengirim maksimal 100 karakter.',
            'customer_id.exists' => 'Pelanggan yang dipilih tidak aktif atau tidak tersedia.',
            'warehouse_id.exists' => 'Gudang yang dipilih tidak aktif atau tidak tersedia.',
            'items.*.product_id.exists' => 'Produk jadi yang dipilih tidak aktif atau tidak tersedia.',
        ];
    }
}
