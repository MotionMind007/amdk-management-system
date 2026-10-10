<?php

namespace App;

enum StockMovementType: string
{
    case OpeningBalance = 'opening_balance';
    case PurchaseReceipt = 'purchase_receipt';
    case ProductionConsumption = 'production_consumption';
    case ProductionOutput = 'production_output';
    case Sale = 'sale';
    case Adjustment = 'adjustment';
    case StockOpname = 'stock_opname';
    case Reversal = 'reversal';

    public function label(): string
    {
        return match ($this) {
            self::OpeningBalance => 'Saldo Awal',
            self::PurchaseReceipt => 'Penerimaan Pembelian',
            self::ProductionConsumption => 'Pemakaian Produksi',
            self::ProductionOutput => 'Hasil Produksi',
            self::Sale => 'Penjualan',
            self::Adjustment => 'Penyesuaian Stok',
            self::StockOpname => 'Stok Opname',
            self::Reversal => 'Pembalikan Transaksi',
        };
    }
}
