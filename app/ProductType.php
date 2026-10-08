<?php

namespace App;

enum ProductType: string
{
    case RawMaterial = 'raw_material';
    case PackagingMaterial = 'packaging_material';
    case SemiFinished = 'semi_finished';
    case FinishedGood = 'finished_good';

    public function label(): string
    {
        return match ($this) {
            self::RawMaterial => 'Bahan Baku',
            self::PackagingMaterial => 'Bahan Kemasan',
            self::SemiFinished => 'Barang Setengah Jadi',
            self::FinishedGood => 'Produk Jadi',
        };
    }
}
