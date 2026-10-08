<?php

namespace App;

enum UserRole: string
{
    case Administrator = 'administrator';
    case Manager = 'manager';
    case Finance = 'finance';
    case Warehouse = 'warehouse';
    case Sales = 'sales';
    case Purchasing = 'purchasing';
    case Production = 'production';

    public function label(): string
    {
        return match ($this) {
            self::Administrator => 'Administrator',
            self::Manager => 'Manager',
            self::Finance => 'Keuangan',
            self::Warehouse => 'Gudang',
            self::Sales => 'Penjualan',
            self::Purchasing => 'Pembelian',
            self::Production => 'Produksi',
        };
    }

    /** @return list<string> */
    public function permissions(): array
    {
        return match ($this) {
            self::Administrator => ['*'],
            self::Manager => ['dashboard.view', 'reports.view', 'sales.view', 'purchasing.view', 'production.view', 'inventory.view', 'finance.view', 'customers.view', 'suppliers.view', 'audit.view'],
            self::Finance => ['dashboard.view', 'finance.view', 'finance.manage', 'receivables.view', 'payables.view', 'reports.view', 'sales.view', 'purchasing.view'],
            self::Warehouse => ['dashboard.view', 'inventory.view', 'inventory.manage', 'production.view'],
            self::Sales => ['dashboard.view', 'sales.view', 'sales.manage', 'customers.view', 'customers.manage', 'receivables.view'],
            self::Purchasing => ['dashboard.view', 'purchasing.view', 'purchasing.manage', 'suppliers.view', 'suppliers.manage', 'payables.view'],
            self::Production => ['dashboard.view', 'production.view', 'production.manage', 'inventory.view'],
        };
    }

    public function allows(string $permission): bool
    {
        return in_array('*', $this->permissions(), true)
            || in_array($permission, $this->permissions(), true);
    }
}
