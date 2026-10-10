<?php

namespace App\Http\Requests;

use App\Models\Warehouse;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class WarehouseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('system.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('warehouses', 'code')->ignore($this->route('warehouse'))],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $warehouse = $this->route('warehouse');

                if (! $warehouse instanceof Warehouse || ! $warehouse->is_active || $this->boolean('is_active')) {
                    return;
                }

                $hasOtherActiveWarehouse = Warehouse::query()
                    ->whereKeyNot($warehouse->getKey())
                    ->where('is_active', true)
                    ->exists();

                if (! $hasOtherActiveWarehouse) {
                    $validator->errors()->add('is_active', 'Minimal satu gudang harus tetap aktif.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.required' => 'Kode gudang wajib diisi.',
            'code.unique' => 'Kode gudang sudah digunakan.',
            'name.required' => 'Nama gudang wajib diisi.',
            'is_active.required' => 'Status gudang wajib dipilih.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => $this->string('code')->trim()->upper()->toString(),
            'name' => $this->string('name')->trim()->toString(),
            'address' => $this->filled('address') ? $this->string('address')->trim()->toString() : null,
        ]);
    }
}
