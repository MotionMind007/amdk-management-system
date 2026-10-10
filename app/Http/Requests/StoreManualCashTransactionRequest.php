<?php

namespace App\Http\Requests;

use App\ExpenseCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManualCashTransactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role->allows('finance.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cash_account_id' => ['required', Rule::exists('cash_accounts', 'id')->where('is_active', true)],
            'transaction_date' => ['required', 'date'],
            'direction' => ['required', Rule::in(['in', 'out'])],
            'expense_category' => ['exclude_unless:direction,out', 'required', Rule::enum(ExpenseCategory::class)],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'expense_category' => 'kategori pengeluaran',
        ];
    }
}
