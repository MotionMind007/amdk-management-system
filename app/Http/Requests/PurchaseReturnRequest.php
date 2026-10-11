<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseReturnRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('purchasing.manage') ?? false;
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
            'return_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:2000'],
            'refund_account_id' => ['nullable', Rule::exists('cash_accounts', 'id')->where('is_active', true)],
            'quantities' => ['required', 'array'],
            'quantities.*' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan retur wajib diisi.',
            'refund_account_id.exists' => 'Akun penerimaan refund tidak aktif atau tidak tersedia.',
        ];
    }
}
