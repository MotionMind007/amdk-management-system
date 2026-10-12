<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\Warehouse;
use App\ProductType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;

class MasterDataImportService
{
    /** @var list<string> */
    public const TYPES = ['products', 'customers', 'suppliers', 'warehouses'];

    /** @var array<string, int>|null */
    private ?array $unitIdsByCode = null;

    /** @return array<string, array{label: string, columns: list<string>, example: list<string>}> */
    public function definitions(): array
    {
        return [
            'products' => [
                'label' => 'Master Produk',
                'columns' => ['sku', 'nama', 'jenis', 'kode_satuan', 'stok_minimum', 'stok_maksimum', 'aktif', 'catatan'],
                'example' => ['RH-600ML', 'Robong Holo 600ml', 'produk_jadi', 'KRT', '10', '500', 'ya', 'Opsional'],
            ],
            'customers' => [
                'label' => 'Pelanggan',
                'columns' => ['kode_pelanggan', 'nama', 'telepon', 'email', 'alamat', 'batas_kredit', 'termin_hari', 'aktif', 'catatan'],
                'example' => ['PLG-001', 'Toko Contoh', '08123456789', 'toko@example.com', 'Jayapura', '5000000', '30', 'ya', 'Opsional'],
            ],
            'suppliers' => [
                'label' => 'Supplier',
                'columns' => ['kode_supplier', 'nama', 'telepon', 'email', 'alamat', 'termin_hari', 'aktif', 'catatan'],
                'example' => ['SUP-001', 'Supplier Contoh', '08123456789', 'supplier@example.com', 'Jayapura', '30', 'ya', 'Opsional'],
            ],
            'warehouses' => [
                'label' => 'Gudang',
                'columns' => ['kode_gudang', 'nama', 'alamat', 'aktif'],
                'example' => ['GDG-02', 'Gudang Kedua', 'Jayapura', 'ya'],
            ],
        ];
    }

    /** @return array{type: string, label: string, rows: list<array{line: int, values: array<string, string>, errors: list<string>, data: array<string, mixed>}>, errors: list<string>, valid_count: int, invalid_count: int} */
    public function preview(string $type, string $path): array
    {
        $definition = $this->definition($type);
        $records = $this->readCsv($path, $definition['columns']);
        $existingCodes = $this->existingCodes($type);
        $seenCodes = [];
        $rows = [];

        foreach ($records as $record) {
            $data = $this->normalize($type, $record['values']);
            $validator = Validator::make($data, $this->rules($type), [], $this->attributes($type));
            $code = (string) ($data[$this->codeField($type)] ?? '');

            if ($code !== '' && isset($existingCodes[$code])) {
                $validator->after(fn ($validator) => $validator->errors()->add($this->codeField($type), 'Kode sudah digunakan.'));
            } elseif ($code !== '' && isset($seenCodes[$code])) {
                $validator->after(fn ($validator) => $validator->errors()->add($this->codeField($type), "Kode duplikat dengan baris {$seenCodes[$code]}."));
            } elseif ($code !== '') {
                $seenCodes[$code] = $record['line'];
            }

            $errors = $validator->errors()->all();
            $rows[] = ['line' => $record['line'], 'values' => $record['values'], 'errors' => $errors, 'data' => $data];
        }

        $invalidCount = count(array_filter($rows, fn (array $row): bool => $row['errors'] !== []));

        return [
            'type' => $type,
            'label' => $definition['label'],
            'rows' => $rows,
            'errors' => $rows === [] ? ['File tidak memiliki baris data.'] : [],
            'valid_count' => count($rows) - $invalidCount,
            'invalid_count' => $invalidCount,
        ];
    }

    /** @param list<array{data: array<string, mixed>}> $rows */
    public function import(string $type, array $rows): int
    {
        return DB::transaction(function () use ($type, $rows): int {
            foreach ($rows as $row) {
                match ($type) {
                    'products' => Product::query()->create($row['data']),
                    'customers' => Customer::query()->create($row['data']),
                    'suppliers' => Supplier::query()->create($row['data']),
                    'warehouses' => Warehouse::query()->create($row['data']),
                    default => throw new RuntimeException('Jenis impor tidak didukung.'),
                };
            }

            return count($rows);
        });
    }

    /** @return array{label: string, columns: list<string>, example: list<string>} */
    public function definition(string $type): array
    {
        return $this->definitions()[$type] ?? throw new RuntimeException('Jenis impor tidak didukung.');
    }

    /**
     * @param  list<string>  $expectedHeaders
     * @return list<array{line: int, values: array<string, string>}>
     */
    private function readCsv(string $path, array $expectedHeaders): array
    {
        $handle = fopen(Storage::disk('local')->path($path), 'rb');

        if ($handle === false) {
            throw new RuntimeException('File impor tidak dapat dibaca.');
        }

        try {
            $firstLine = fgets($handle);
            if ($firstLine === false) {
                return [];
            }

            $delimiter = $this->detectDelimiter($firstLine);
            rewind($handle);
            $headers = fgetcsv($handle, null, $delimiter, '"', '\\');
            $headers = array_map(fn (?string $header): string => mb_strtolower(trim(str_replace("\xEF\xBB\xBF", '', (string) $header))), $headers ?: []);

            if ($headers !== $expectedHeaders) {
                throw new RuntimeException('Kolom CSV tidak sesuai template. Unduh dan gunakan template terbaru tanpa mengubah nama kolom.');
            }

            $records = [];
            $line = 1;
            while (($values = fgetcsv($handle, null, $delimiter, '"', '\\')) !== false) {
                $line++;
                $values = array_map(fn (?string $value): string => trim((string) $value), $values);

                if (count(array_filter($values, fn (string $value): bool => $value !== '')) === 0) {
                    continue;
                }

                if (count($values) !== count($headers)) {
                    throw new RuntimeException("Jumlah kolom pada baris {$line} tidak sesuai template.");
                }

                if (count($records) >= 1000) {
                    throw new RuntimeException('Maksimal 1.000 baris per proses impor.');
                }

                $records[] = ['line' => $line, 'values' => array_combine($headers, $values)];
            }

            return $records;
        } finally {
            fclose($handle);
        }
    }

    private function detectDelimiter(string $line): string
    {
        $counts = [',' => substr_count($line, ','), ';' => substr_count($line, ';'), "\t" => substr_count($line, "\t")];
        arsort($counts);

        return (string) array_key_first($counts);
    }

    /**
     * @param  array<string, string>  $values
     * @return array<string, mixed>
     */
    private function normalize(string $type, array $values): array
    {
        return match ($type) {
            'products' => [
                'sku' => mb_strtoupper($values['sku']),
                'name' => $values['nama'],
                'unit_id' => $this->unitId($values['kode_satuan']),
                'type' => $this->productType($values['jenis']),
                'minimum_stock' => $values['stok_minimum'],
                'maximum_stock' => $values['stok_maksimum'] !== '' ? $values['stok_maksimum'] : null,
                'is_active' => $this->booleanValue($values['aktif']),
                'notes' => $values['catatan'] !== '' ? $values['catatan'] : null,
            ],
            'customers' => [
                'customer_code' => mb_strtoupper($values['kode_pelanggan']),
                'name' => $values['nama'],
                'phone' => $this->nullable($values['telepon']),
                'email' => $values['email'] !== '' ? mb_strtolower($values['email']) : null,
                'address' => $this->nullable($values['alamat']),
                'credit_limit' => $values['batas_kredit'],
                'payment_term' => $values['termin_hari'],
                'status' => $this->statusValue($values['aktif']),
                'notes' => $this->nullable($values['catatan']),
            ],
            'suppliers' => [
                'supplier_code' => mb_strtoupper($values['kode_supplier']),
                'name' => $values['nama'],
                'phone' => $this->nullable($values['telepon']),
                'email' => $values['email'] !== '' ? mb_strtolower($values['email']) : null,
                'address' => $this->nullable($values['alamat']),
                'payment_term' => $values['termin_hari'],
                'status' => $this->statusValue($values['aktif']),
                'notes' => $this->nullable($values['catatan']),
            ],
            'warehouses' => [
                'code' => mb_strtoupper($values['kode_gudang']),
                'name' => $values['nama'],
                'address' => $this->nullable($values['alamat']),
                'is_active' => $this->booleanValue($values['aktif']),
            ],
            default => [],
        };
    }

    /** @return array<string, mixed> */
    private function rules(string $type): array
    {
        return match ($type) {
            'products' => [
                'sku' => ['required', 'string', 'max:50'],
                'name' => ['required', 'string', 'max:255'],
                'unit_id' => ['required', 'integer', 'exists:units,id'],
                'type' => ['required', Rule::enum(ProductType::class)],
                'minimum_stock' => ['required', 'numeric', 'min:0'],
                'maximum_stock' => ['nullable', 'numeric', 'gte:minimum_stock'],
                'is_active' => ['required', 'boolean'],
                'notes' => ['nullable', 'string', 'max:2000'],
            ],
            'customers' => [
                'customer_code' => ['required', 'string', 'max:30'],
                'name' => ['required', 'string', 'max:255'],
                'phone' => ['nullable', 'string', 'max:30'],
                'email' => ['nullable', 'email', 'max:255'],
                'address' => ['nullable', 'string', 'max:2000'],
                'credit_limit' => ['required', 'numeric', 'min:0', 'max:9999999999999999.99'],
                'payment_term' => ['required', 'integer', 'min:0', 'max:365'],
                'status' => ['required', Rule::in(['active', 'inactive'])],
                'notes' => ['nullable', 'string', 'max:2000'],
            ],
            'suppliers' => [
                'supplier_code' => ['required', 'string', 'max:30'],
                'name' => ['required', 'string', 'max:255'],
                'phone' => ['nullable', 'string', 'max:30'],
                'email' => ['nullable', 'email', 'max:255'],
                'address' => ['nullable', 'string', 'max:2000'],
                'payment_term' => ['required', 'integer', 'min:0', 'max:365'],
                'status' => ['required', Rule::in(['active', 'inactive'])],
                'notes' => ['nullable', 'string', 'max:2000'],
            ],
            'warehouses' => [
                'code' => ['required', 'string', 'max:30'],
                'name' => ['required', 'string', 'max:255'],
                'address' => ['nullable', 'string', 'max:2000'],
                'is_active' => ['required', 'boolean'],
            ],
            default => [],
        };
    }

    /** @return array<string, string> */
    private function attributes(string $type): array
    {
        return match ($type) {
            'products' => ['sku' => 'SKU', 'name' => 'nama', 'unit_id' => 'kode satuan', 'type' => 'jenis', 'minimum_stock' => 'stok minimum', 'maximum_stock' => 'stok maksimum', 'is_active' => 'aktif'],
            'customers' => ['customer_code' => 'kode pelanggan', 'name' => 'nama', 'credit_limit' => 'batas kredit', 'payment_term' => 'termin hari', 'status' => 'aktif'],
            'suppliers' => ['supplier_code' => 'kode supplier', 'name' => 'nama', 'payment_term' => 'termin hari', 'status' => 'aktif'],
            'warehouses' => ['code' => 'kode gudang', 'name' => 'nama', 'is_active' => 'aktif'],
            default => [],
        };
    }

    private function codeField(string $type): string
    {
        return match ($type) {
            'products' => 'sku',
            'customers' => 'customer_code',
            'suppliers' => 'supplier_code',
            'warehouses' => 'code',
            default => 'code',
        };
    }

    /** @return array<string, true> */
    private function existingCodes(string $type): array
    {
        [$table, $column] = match ($type) {
            'products' => ['products', 'sku'],
            'customers' => ['customers', 'customer_code'],
            'suppliers' => ['suppliers', 'supplier_code'],
            'warehouses' => ['warehouses', 'code'],
            default => throw new RuntimeException('Jenis impor tidak didukung.'),
        };

        return DB::table($table)
            ->pluck($column)
            ->mapWithKeys(fn (string $code): array => [mb_strtoupper($code) => true])
            ->all();
    }

    private function unitId(string $code): ?int
    {
        $this->unitIdsByCode ??= Unit::query()
            ->get(['id', 'code'])
            ->mapWithKeys(fn (Unit $unit): array => [mb_strtoupper($unit->code) => $unit->id])
            ->all();

        return $this->unitIdsByCode[mb_strtoupper($code)] ?? null;
    }

    private function booleanValue(string $value): ?bool
    {
        return match (mb_strtolower(trim($value))) {
            'ya', '1', 'aktif', 'active', 'true' => true,
            'tidak', '0', 'nonaktif', 'inactive', 'false' => false,
            default => null,
        };
    }

    private function statusValue(string $value): ?string
    {
        return match ($this->booleanValue($value)) {
            true => 'active',
            false => 'inactive',
            null => null,
        };
    }

    private function productType(string $value): ?string
    {
        return match (mb_strtolower(trim($value))) {
            'bahan_baku', 'raw_material' => ProductType::RawMaterial->value,
            'bahan_kemasan', 'packaging_material' => ProductType::PackagingMaterial->value,
            'barang_setengah_jadi', 'semi_finished' => ProductType::SemiFinished->value,
            'produk_jadi', 'finished_good' => ProductType::FinishedGood->value,
            default => null,
        };
    }

    private function nullable(string $value): ?string
    {
        return $value !== '' ? $value : null;
    }
}
