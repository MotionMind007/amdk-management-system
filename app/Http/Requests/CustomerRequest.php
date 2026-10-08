<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('customers.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_code' => ['required', 'string', 'max:30', Rule::unique('customers')->ignore($this->route('customer'))],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'credit_limit' => ['required', 'numeric', 'min:0', 'max:9999999999999999.99'],
            'payment_term' => ['required', 'integer', 'min:0', 'max:365'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_code.required' => 'Kode pelanggan wajib diisi.',
            'customer_code.unique' => 'Kode pelanggan sudah digunakan.',
            'name.required' => 'Nama pelanggan wajib diisi.',
            'credit_limit.min' => 'Batas kredit tidak boleh negatif.',
            'payment_term.max' => 'Termin pembayaran maksimal 365 hari.',
        ];
    }
}
