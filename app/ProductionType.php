<?php

namespace App;

enum ProductionType: string
{
    case FinishedGood = 'finished_good';
    case Packaging = 'packaging';

    public function label(): string
    {
        return match ($this) {
            self::FinishedGood => 'Produk Jadi',
            self::Packaging => 'Kemasan / Blowing',
        };
    }
}
