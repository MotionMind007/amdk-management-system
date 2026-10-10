<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StockOpnameRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('inventory.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $uniquePeriod = Rule::unique('stock_opnames', 'period')
            ->where(fn ($query) => $query->where('warehouse_id', $this->input('warehouse_id')))
            ->ignore($this->route('stockOpname'));

        return [
            'period' => ['required', 'date_format:Y-m', $uniquePeriod],
            'opname_date' => ['required', 'date', 'before_or_equal:today'],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'array:product_id,physical_quantity,notes'],
            'items.*.product_id' => ['required', 'distinct:strict', Rule::exists('products', 'id')->where('is_active', true)],
            'items.*.physical_quantity' => ['required', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $period = $this->string('period')->toString();
                $opnameDate = $this->string('opname_date')->toString();

                if ($period !== '' && $period > today()->format('Y-m')) {
                    $validator->errors()->add('period', 'Periode stok opname tidak boleh melewati bulan berjalan.');
                }

                if ($period !== '' && $opnameDate !== '' && substr($opnameDate, 0, 7) !== $period) {
                    $validator->errors()->add('opname_date', 'Tanggal opname harus berada dalam periode yang dipilih.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'period.required' => 'Periode stok opname wajib diisi.',
            'period.date_format' => 'Format periode stok opname tidak valid.',
            'period.unique' => 'Stok opname untuk gudang dan periode tersebut sudah tersedia.',
            'opname_date.required' => 'Tanggal stok opname wajib diisi.',
            'opname_date.before_or_equal' => 'Tanggal stok opname tidak boleh melewati hari ini.',
            'warehouse_id.required' => 'Gudang wajib dipilih.',
            'warehouse_id.exists' => 'Gudang yang dipilih tidak tersedia.',
            'items.required' => 'Minimal satu produk atau bahan wajib dihitung.',
            'items.min' => 'Minimal satu produk atau bahan wajib dihitung.',
            'items.*.product_id.required' => 'Produk atau bahan wajib dipilih.',
            'items.*.product_id.distinct' => 'Produk atau bahan tidak boleh dihitung dua kali.',
            'items.*.product_id.exists' => 'Produk atau bahan yang dipilih tidak tersedia.',
            'items.*.physical_quantity.required' => 'Hasil stok fisik wajib diisi.',
            'items.*.physical_quantity.numeric' => 'Hasil stok fisik harus berupa angka.',
            'items.*.physical_quantity.min' => 'Hasil stok fisik tidak boleh kurang dari nol.',
        ];
    }
}
