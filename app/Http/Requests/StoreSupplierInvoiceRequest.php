<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreSupplierInvoiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('finance.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'submission_token' => ['sometimes', 'nullable', 'uuid'],
            'supplier_invoice_number' => [
                'required',
                'string',
                'max:100',
                Rule::unique('supplier_invoices')->where('purchase_order_id', $this->route('goodsReceipt')?->purchase_order_id),
            ],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'total' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'proof' => ['nullable', File::types(['jpg', 'jpeg', 'png', 'webp', 'pdf'])->max('5mb')],
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_invoice_number.required' => 'Nomor faktur supplier wajib diisi.',
            'supplier_invoice_number.unique' => 'Nomor faktur supplier sudah digunakan pada PO ini.',
            'invoice_date.required' => 'Tanggal faktur supplier wajib diisi.',
            'due_date.after_or_equal' => 'Tanggal jatuh tempo tidak boleh sebelum tanggal faktur.',
            'total.required' => 'Nilai tagihan wajib diisi.',
        ];
    }
}
