<?php

namespace App\Http\Requests\Admin;

use App\Services\MasterDataImportService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class PreviewDataImportRequest extends FormRequest
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
            'type' => ['required', 'string', 'in:'.implode(',', MasterDataImportService::TYPES)],
            'file' => ['required', File::types(['csv', 'txt'])->max('5mb'), 'extensions:csv,txt'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'type.required' => 'Jenis data wajib dipilih.',
            'type.in' => 'Jenis data tidak didukung.',
            'file.required' => 'File CSV wajib dipilih.',
            'file.mimes' => 'File harus berformat CSV.',
            'file.extensions' => 'Ekstensi file harus .csv atau .txt.',
            'file.max' => 'Ukuran file maksimal 5 MB.',
        ];
    }
}
