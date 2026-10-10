<?php

namespace App\Models;

use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'action', 'module', 'description', 'entity_type', 'entity_id', 'old_values', 'new_values', 'ip_address', 'user_agent'])]
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function summary(): string
    {
        if (filled($this->description)) {
            return $this->description;
        }

        $identifier = data_get($this->new_values, 'number')
            ?? data_get($this->new_values, 'name')
            ?? data_get($this->new_values, 'reference_number');
        $summary = self::actionLabelFor($this->action).' '.self::moduleLabelFor($this->module);

        return $identifier ? "{$summary} {$identifier}." : "{$summary}.";
    }

    public static function actionLabelFor(string $action): string
    {
        return match ($action) {
            'CREATE' => 'Menambahkan',
            'UPDATE' => 'Memperbarui',
            'DELETE' => 'Menghapus',
            'DEACTIVATE' => 'Menonaktifkan',
            'POST' => 'Memposting',
            'APPROVE' => 'Menyetujui',
            'RECEIVE' => 'Menerima',
            'PAYMENT' => 'Mencatat pembayaran',
            'ADJUST' => 'Menyesuaikan',
            'CHANGE_PASSWORD' => 'Mengganti password',
            'LOGIN' => 'Masuk ke sistem',
            'LOGOUT' => 'Keluar dari sistem',
            default => str($action)->replace('_', ' ')->lower()->ucfirst()->toString(),
        };
    }

    public static function moduleLabelFor(string $module): string
    {
        return match ($module) {
            'Authentication' => 'akun',
            'Customer' => 'pelanggan',
            'Employee' => 'karyawan',
            'Finance' => 'transaksi keuangan',
            'GoodsReceipt' => 'penerimaan barang',
            'Inventory' => 'stok',
            'Opening Stock' => 'stok awal',
            'Payable' => 'hutang',
            'Product' => 'produk',
            'ProductComposition' => 'komposisi produk',
            'Production' => 'produksi',
            'PurchaseOrder' => 'purchase order',
            'Receivable' => 'piutang',
            'Sale' => 'penjualan',
            'Stock Opname' => 'stok opname',
            'Supplier' => 'supplier',
            'User' => 'user',
            'Warehouse' => 'gudang',
            default => str($module)->headline()->lower()->toString(),
        };
    }
}
