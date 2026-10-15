<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDeliveryNoteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('sales.deliver') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'submission_token' => ['required', 'uuid'],
            'delivery_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'quantities' => ['required', 'array'],
            'quantities.*' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'submission_token.required' => 'Token transaksi tidak tersedia. Muat ulang halaman lalu coba kembali.',
            'delivery_date.required' => 'Tanggal pengiriman wajib diisi.',
            'quantities.required' => 'Jumlah barang yang dikirim wajib diisi.',
        ];
    }
}
