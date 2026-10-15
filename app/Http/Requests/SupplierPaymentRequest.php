<?php

namespace App\Http\Requests;

use App\Models\CashAccount;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

class SupplierPaymentRequest extends FormRequest
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
            'submission_token' => ['required', 'uuid'],
            'cash_account_id' => ['required', Rule::exists('cash_accounts', 'id')->where('is_active', true)],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'proof' => ['nullable', File::types(['jpg', 'jpeg', 'png', 'webp', 'pdf'])->max('5mb')],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('cash_account_id')) {
                return;
            }

            $account = CashAccount::query()->find($this->integer('cash_account_id'));
            if ($account?->type === 'bank' && ! $this->hasFile('proof')) {
                $validator->errors()->add('proof', 'Bukti transfer wajib diunggah untuk pembayaran dari rekening bank.');
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'submission_token.required' => 'Token transaksi tidak tersedia. Muat ulang halaman lalu coba kembali.',
        ];
    }
}
