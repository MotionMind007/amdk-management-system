<?php

namespace App;

enum ExpenseCategory: string
{
    case Operational = 'operational';
    case Salary = 'salary';
    case Electricity = 'electricity';
    case FuelTransportation = 'fuel_transportation';
    case Maintenance = 'maintenance';
    case TaxFees = 'tax_fees';
    case OfficeSupplies = 'office_supplies';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Operational => 'Operasional',
            self::Salary => 'Gaji / Upah',
            self::Electricity => 'Listrik',
            self::FuelTransportation => 'BBM / Transportasi',
            self::Maintenance => 'Pemeliharaan Mesin',
            self::TaxFees => 'Pajak / Retribusi',
            self::OfficeSupplies => 'Perlengkapan Kantor',
            self::Other => 'Lainnya',
        };
    }
}
