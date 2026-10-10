<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreGoodsReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('purchasing.receive') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'quantities' => ['required', 'array'],
            'quantities.*' => ['nullable', 'numeric', 'min:0'],
            'proof' => ['required', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max('5mb')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'proof.required' => 'Bukti penerimaan barang wajib diunggah.',
            'proof.image' => 'Bukti penerimaan harus berupa gambar.',
            'proof.mimes' => 'Format bukti penerimaan harus JPG, JPEG, PNG, atau WEBP.',
            'proof.max' => 'Ukuran bukti penerimaan maksimal 5 MB.',
        ];
    }
}
