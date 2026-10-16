<?php

namespace App\Services;

use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\Customer;
use App\Models\OpeningStock;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\ProductType;
use App\UserRole;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class MasterDataImportService
{
    /** @var list<string> */
    public const TYPES = ['products', 'customers', 'suppliers', 'warehouses', 'employees', 'opening_stocks', 'cash_balances'];

    /** @var array<string, int>|null */
    private ?array $unitIdsByCode = null;

    /** @var array<string, int>|null */
    private ?array $productIdsBySku = null;

    /** @var array<string, int>|null */
    private ?array $warehouseIdsByCode = null;

    public function __construct(
        private readonly DocumentNumberService $documentNumbers,
        private readonly AccountingPeriodService $accountingPeriods,
    ) {}

    /** @return array<string, array{label: string, columns: list<string>, example: list<string>, description: string, masked_columns?: list<string>}> */
    public function definitions(): array
    {
        return [
            'products' => [
                'label' => 'Master Produk',
                'description' => 'Produk jadi, bahan baku, bahan kemasan, dan barang setengah jadi.',
                'columns' => ['sku', 'nama', 'jenis', 'kode_satuan', 'stok_minimum', 'stok_maksimum', 'aktif', 'catatan'],
                'example' => ['RH-600ML', 'Robong Holo 600ml', 'produk_jadi', 'KRT', '10', '500', 'ya', 'Opsional'],
            ],
            'customers' => [
                'label' => 'Pelanggan',
                'description' => 'Identitas pelanggan, batas kredit, dan termin pembayaran.',
                'columns' => ['kode_pelanggan', 'nama', 'telepon', 'email', 'alamat', 'batas_kredit', 'termin_hari', 'aktif', 'catatan'],
                'example' => ['PLG-001', 'Toko Contoh', '08123456789', 'toko@example.com', 'Jayapura', '5000000', '30', 'ya', 'Opsional'],
            ],
            'suppliers' => [
                'label' => 'Supplier',
                'description' => 'Identitas pemasok dan termin pembayaran.',
                'columns' => ['kode_supplier', 'nama', 'telepon', 'email', 'alamat', 'termin_hari', 'aktif', 'catatan'],
                'example' => ['SUP-001', 'Supplier Contoh', '08123456789', 'supplier@example.com', 'Jayapura', '30', 'ya', 'Opsional'],
            ],
            'warehouses' => [
                'label' => 'Gudang',
                'description' => 'Daftar lokasi penyimpanan aktif maupun nonaktif.',
                'columns' => ['kode_gudang', 'nama', 'alamat', 'aktif'],
                'example' => ['GDG-02', 'Gudang Kedua', 'Jayapura', 'ya'],
            ],
            'employees' => [
                'label' => 'Karyawan & User Login',
                'description' => 'Membuat data karyawan sekaligus akun login. Password hanya dipakai saat impor dan tidak ditampilkan di preview.',
                'columns' => ['kode_karyawan', 'nama', 'telepon', 'email_login', 'alamat', 'jabatan', 'departemen', 'tanggal_masuk', 'role', 'password_awal', 'aktif'],
                'example' => ['KRY-001', 'Karyawan Contoh', '08123456789', 'karyawan@example.com', 'Jayapura', 'Operator', 'Gudang', '2026-01-01', 'warehouse', 'GantiPassword123!', 'ya'],
                'masked_columns' => ['password_awal'],
            ],
            'opening_stocks' => [
                'label' => 'Stok Awal',
                'description' => 'Membuat draft stok awal per tanggal dan gudang. Draft wajib diperiksa lalu diposting dari menu Stok Awal.',
                'columns' => ['tanggal_stok', 'kode_gudang', 'sku', 'jumlah', 'catatan'],
                'example' => ['2026-01-01', 'GDG-01', 'RH-600ML', '100', 'Hasil hitung fisik saat cutover'],
            ],
            'cash_balances' => [
                'label' => 'Saldo Awal Kas/Bank',
                'description' => 'Membuat akun kas atau bank baru beserta transaksi saldo awal yang tercatat di ledger.',
                'columns' => ['kode_akun', 'nama_akun', 'jenis', 'tanggal_saldo', 'saldo_awal', 'aktif'],
                'example' => ['BANK-BRI', 'Bank BRI', 'bank', '2026-01-01', '10000000', 'ya'],
            ],
        ];
    }

    /** @return array{type: string, label: string, rows: list<array{line: int, values: array<string, string>, errors: list<string>, data: array<string, mixed>}>, errors: list<string>, valid_count: int, invalid_count: int} */
    public function preview(string $type, string $path): array
    {
        $definition = $this->definition($type);
        $records = $this->readCsv($path, $definition['columns']);
        $seenIdentities = [];
        $seenEmployeeEmails = [];
        $rows = [];

        foreach ($records as $record) {
            $data = $this->normalize($type, $record['values']);
            $validator = Validator::make($data, $this->rules($type), [], $this->attributes($type));
            $identity = $this->rowIdentity($type, $data);

            if ($identity !== '' && isset($seenIdentities[$identity])) {
                $message = $type === 'opening_stocks'
                    ? "Data duplikat dengan baris {$seenIdentities[$identity]}."
                    : "Kode duplikat dengan baris {$seenIdentities[$identity]}.";
                $validator->after(fn ($validator) => $validator->errors()->add('row', $message));
            } elseif ($identity !== '') {
                $seenIdentities[$identity] = $record['line'];
            }

            if ($type === 'employees' && ($data['email'] ?? '') !== '') {
                $email = (string) $data['email'];
                if (isset($seenEmployeeEmails[$email])) {
                    $validator->after(fn ($validator) => $validator->errors()->add('email', "Email duplikat dengan baris {$seenEmployeeEmails[$email]}."));
                } else {
                    $seenEmployeeEmails[$email] = $record['line'];
                }
            }

            if ($type === 'opening_stocks' && $data['product_id'] !== null && $data['warehouse_id'] !== null) {
                $hasMovement = DB::table('stock_movements')
                    ->where('product_id', $data['product_id'])
                    ->where('warehouse_id', $data['warehouse_id'])
                    ->exists();
                $hasOpeningDraft = DB::table('opening_stock_items')
                    ->join('opening_stocks', 'opening_stocks.id', '=', 'opening_stock_items.opening_stock_id')
                    ->where('opening_stock_items.product_id', $data['product_id'])
                    ->where('opening_stocks.warehouse_id', $data['warehouse_id'])
                    ->where('opening_stocks.status', 'draft')
                    ->exists();

                if ($hasMovement) {
                    $validator->after(fn ($validator) => $validator->errors()->add('product_id', 'Produk sudah memiliki pergerakan stok di gudang ini. Gunakan Penyesuaian Stok, bukan Stok Awal.'));
                } elseif ($hasOpeningDraft) {
                    $validator->after(fn ($validator) => $validator->errors()->add('product_id', 'Produk sudah terdapat pada draft stok awal di gudang ini.'));
                }
            }

            if (in_array($type, ['opening_stocks', 'cash_balances'], true)) {
                $date = $data[$type === 'opening_stocks' ? 'stock_date' : 'transaction_date'] ?? null;
                $parsedDate = is_string($date) ? date_create($date) : false;

                if ($parsedDate !== false && DB::table('accounting_periods')
                    ->where('period', $parsedDate->format('Y-m'))
                    ->where('status', 'closed')
                    ->exists()) {
                    $validator->after(fn ($validator) => $validator->errors()->add('date', "Periode {$parsedDate->format('Y-m')} sudah ditutup."));
                }
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
    public function import(string $type, array $rows, User $user): int
    {
        return DB::transaction(function () use ($type, $rows, $user): int {
            if ($type === 'opening_stocks') {
                return $this->importOpeningStocks($rows, $user);
            }

            foreach ($rows as $row) {
                match ($type) {
                    'products' => Product::query()->create($row['data']),
                    'customers' => Customer::query()->create($row['data']),
                    'suppliers' => Supplier::query()->create($row['data']),
                    'warehouses' => Warehouse::query()->create($row['data']),
                    'employees' => $this->importEmployee($row['data']),
                    'cash_balances' => $this->importCashBalance($row['data'], $user),
                    default => throw new RuntimeException('Jenis impor tidak didukung.'),
                };
            }

            return count($rows);
        });
    }

    /** @return array{label: string, columns: list<string>, example: list<string>, description: string, masked_columns?: list<string>} */
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
            'employees' => [
                'employee_code' => mb_strtoupper($values['kode_karyawan']),
                'name' => $values['nama'],
                'phone' => $this->nullable($values['telepon']),
                'email' => mb_strtolower($values['email_login']),
                'address' => $this->nullable($values['alamat']),
                'position' => $values['jabatan'],
                'department' => $values['departemen'],
                'join_date' => $this->nullable($values['tanggal_masuk']),
                'role' => mb_strtolower($values['role']),
                'password' => $values['password_awal'],
                'status' => $this->statusValue($values['aktif']),
            ],
            'opening_stocks' => [
                'stock_date' => $values['tanggal_stok'],
                'warehouse_id' => $this->warehouseId($values['kode_gudang']),
                'product_id' => $this->productId($values['sku']),
                'quantity' => $values['jumlah'],
                'notes' => $this->nullable($values['catatan']),
            ],
            'cash_balances' => [
                'code' => mb_strtoupper($values['kode_akun']),
                'name' => $values['nama_akun'],
                'type' => mb_strtolower($values['jenis']),
                'transaction_date' => $values['tanggal_saldo'],
                'balance' => $values['saldo_awal'],
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
                'sku' => ['required', 'string', 'max:50', Rule::unique('products', 'sku')],
                'name' => ['required', 'string', 'max:255'],
                'unit_id' => ['required', 'integer', 'exists:units,id'],
                'type' => ['required', Rule::enum(ProductType::class)],
                'minimum_stock' => ['required', 'numeric', 'min:0'],
                'maximum_stock' => ['nullable', 'numeric', 'gte:minimum_stock'],
                'is_active' => ['required', 'boolean'],
                'notes' => ['nullable', 'string', 'max:2000'],
            ],
            'customers' => [
                'customer_code' => ['required', 'string', 'max:30', Rule::unique('customers', 'customer_code')],
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
                'supplier_code' => ['required', 'string', 'max:30', Rule::unique('suppliers', 'supplier_code')],
                'name' => ['required', 'string', 'max:255'],
                'phone' => ['nullable', 'string', 'max:30'],
                'email' => ['nullable', 'email', 'max:255'],
                'address' => ['nullable', 'string', 'max:2000'],
                'payment_term' => ['required', 'integer', 'min:0', 'max:365'],
                'status' => ['required', Rule::in(['active', 'inactive'])],
                'notes' => ['nullable', 'string', 'max:2000'],
            ],
            'warehouses' => [
                'code' => ['required', 'string', 'max:30', Rule::unique('warehouses', 'code')],
                'name' => ['required', 'string', 'max:255'],
                'address' => ['nullable', 'string', 'max:2000'],
                'is_active' => ['required', 'boolean'],
            ],
            'employees' => [
                'employee_code' => ['required', 'string', 'max:30', Rule::unique('employees', 'employee_code')],
                'name' => ['required', 'string', 'max:255'],
                'phone' => ['nullable', 'string', 'max:30'],
                'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email'), Rule::unique('employees', 'email')],
                'address' => ['nullable', 'string', 'max:2000'],
                'position' => ['required', 'string', 'max:255'],
                'department' => ['required', 'string', 'max:255'],
                'join_date' => ['nullable', 'date'],
                'role' => ['required', Rule::enum(UserRole::class)],
                'password' => ['required', Password::defaults()],
                'status' => ['required', Rule::in(['active', 'inactive'])],
            ],
            'opening_stocks' => [
                'stock_date' => ['required', 'date', 'before_or_equal:today'],
                'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('is_active', true)],
                'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('is_active', true)->whereNull('deleted_at')],
                'quantity' => ['required', 'numeric', 'gt:0'],
                'notes' => ['nullable', 'string', 'max:2000'],
            ],
            'cash_balances' => [
                'code' => ['required', 'string', 'max:30', Rule::unique('cash_accounts', 'code')],
                'name' => ['required', 'string', 'max:255'],
                'type' => ['required', Rule::in(['cash', 'bank'])],
                'transaction_date' => ['required', 'date', 'before_or_equal:today'],
                'balance' => ['required', 'numeric', 'min:0', 'max:9999999999999999.99'],
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
            'employees' => ['employee_code' => 'kode karyawan', 'name' => 'nama', 'email' => 'email login', 'position' => 'jabatan', 'department' => 'departemen', 'join_date' => 'tanggal masuk', 'role' => 'role', 'password' => 'password awal', 'status' => 'aktif'],
            'opening_stocks' => ['stock_date' => 'tanggal stok', 'warehouse_id' => 'kode gudang', 'product_id' => 'SKU', 'quantity' => 'jumlah'],
            'cash_balances' => ['code' => 'kode akun', 'name' => 'nama akun', 'type' => 'jenis', 'transaction_date' => 'tanggal saldo', 'balance' => 'saldo awal', 'is_active' => 'aktif'],
            default => [],
        };
    }

    /** @param array<string, mixed> $data */
    private function rowIdentity(string $type, array $data): string
    {
        return match ($type) {
            'products' => (string) ($data['sku'] ?? ''),
            'customers' => (string) ($data['customer_code'] ?? ''),
            'suppliers' => (string) ($data['supplier_code'] ?? ''),
            'warehouses', 'cash_balances' => (string) ($data['code'] ?? ''),
            'employees' => (string) ($data['employee_code'] ?? ''),
            'opening_stocks' => implode('|', [$data['stock_date'] ?? '', $data['warehouse_id'] ?? '', $data['product_id'] ?? '']),
            default => '',
        };
    }

    private function unitId(string $code): ?int
    {
        $this->unitIdsByCode ??= Unit::query()
            ->get(['id', 'code'])
            ->mapWithKeys(fn (Unit $unit): array => [mb_strtoupper($unit->code) => $unit->id])
            ->all();

        return $this->unitIdsByCode[mb_strtoupper($code)] ?? null;
    }

    private function productId(string $sku): ?int
    {
        $this->productIdsBySku ??= Product::query()
            ->where('is_active', true)
            ->get(['id', 'sku'])
            ->mapWithKeys(fn (Product $product): array => [mb_strtoupper($product->sku) => $product->id])
            ->all();

        return $this->productIdsBySku[mb_strtoupper($sku)] ?? null;
    }

    private function warehouseId(string $code): ?int
    {
        $this->warehouseIdsByCode ??= Warehouse::query()
            ->where('is_active', true)
            ->get(['id', 'code'])
            ->mapWithKeys(fn (Warehouse $warehouse): array => [mb_strtoupper($warehouse->code) => $warehouse->id])
            ->all();

        return $this->warehouseIdsByCode[mb_strtoupper($code)] ?? null;
    }

    /** @param array<string, mixed> $data */
    private function importEmployee(array $data): void
    {
        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
            'is_active' => $data['status'] === 'active',
        ]);

        unset($data['password'], $data['role']);
        $user->employee()->create($data);
    }

    /** @param list<array{data: array<string, mixed>}> $rows */
    private function importOpeningStocks(array $rows, User $user): int
    {
        $groups = collect($rows)->groupBy(fn (array $row): string => $row['data']['stock_date'].'|'.$row['data']['warehouse_id']);

        foreach ($groups as $group) {
            $first = $group->first()['data'];
            $openingStock = OpeningStock::query()->create([
                'number' => $this->documentNumbers->next('OPEN', Carbon::parse($first['stock_date'])->year),
                'stock_date' => $first['stock_date'],
                'warehouse_id' => $first['warehouse_id'],
                'status' => 'draft',
                'notes' => collect($group)->pluck('data.notes')->filter()->unique()->implode('; ') ?: 'Impor migrasi data awal',
                'created_by' => $user->id,
            ]);

            $openingStock->items()->createMany(collect($group)->map(fn (array $row): array => [
                'product_id' => $row['data']['product_id'],
                'quantity' => $row['data']['quantity'],
            ])->all());
        }

        return count($rows);
    }

    /** @param array<string, mixed> $data */
    private function importCashBalance(array $data, User $user): void
    {
        $this->accountingPeriods->ensureOpen($data['transaction_date']);
        $balance = (string) $data['balance'];
        $account = CashAccount::query()->create([
            'code' => $data['code'],
            'name' => $data['name'],
            'type' => $data['type'],
            'balance' => $balance,
            'is_active' => $data['is_active'],
        ]);

        if (bccomp($balance, '0', 2) === 1) {
            CashTransaction::query()->create([
                'cash_account_id' => $account->id,
                'transaction_date' => $data['transaction_date'],
                'direction' => 'in',
                'amount' => $balance,
                'reference_number' => $this->documentNumbers->next('CASH-OPEN', Carbon::parse($data['transaction_date'])->year),
                'description' => 'Saldo awal migrasi '.$account->name,
                'user_id' => $user->id,
            ]);
        }
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
