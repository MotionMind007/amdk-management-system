<?php

namespace App\Http\Requests;

use App\Models\CashAccount;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

class CustomerPaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('sales.manage') || $this->user()?->hasPermission('finance.manage');
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
            'payment_method' => ['required', Rule::in(['cash', 'transfer'])],
            'sender_bank' => [
                Rule::excludeIf(fn (): bool => $this->input('payment_method') !== 'transfer'),
                'required',
                'string',
                'max:100',
            ],
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
            if ($validator->errors()->hasAny(['cash_account_id', 'payment_method'])) {
                return;
            }

            $account = CashAccount::query()->find($this->integer('cash_account_id'));
            $isTransfer = $this->input('payment_method') === 'transfer';
            $expectedAccountType = $isTransfer ? 'bank' : 'cash';

            if ($account?->type !== $expectedAccountType) {
                $validator->errors()->add(
                    'cash_account_id',
                    $isTransfer ? 'Pilih akun bank untuk pembayaran transfer.' : 'Pilih akun kas untuk pembayaran cash.',
                );
            }

            if ($isTransfer && ! $this->hasFile('proof')) {
                $validator->errors()->add('proof', 'Bukti transfer wajib diunggah untuk pembayaran transfer.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'payment_method.required' => 'Metode pembayaran wajib dipilih.',
            'payment_method.in' => 'Metode pembayaran yang dipilih tidak tersedia.',
            'sender_bank.required' => 'Nama bank pengirim wajib diisi untuk pembayaran transfer.',
            'sender_bank.max' => 'Nama bank pengirim maksimal 100 karakter.',
        ];
    }
}
