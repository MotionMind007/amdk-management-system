<?php

namespace App;

enum UserRole: string
{
    case Admin = 'admin';
    case Warehouse = 'warehouse';
    case Sales = 'sales';
    case SuperAdministrator = 'super_admin';
    case Owner = 'owner';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Warehouse => 'Gudang',
            self::Sales => 'Sales',
            self::SuperAdministrator => 'Super Admin',
            self::Owner => 'Owner',
        };
    }

    /** @return list<string> */
    public function permissions(): array
    {
        return match ($this) {
            self::Admin => ['dashboard.view', 'reports.view', 'sales.view', 'sales.manage', 'purchasing.view', 'purchasing.manage', 'inventory.view', 'inventory.manage', 'finance.view', 'finance.manage', 'customers.view', 'customers.manage', 'suppliers.view', 'suppliers.manage', 'receivables.view', 'payables.view', 'employees.manage'],
            self::Warehouse => ['dashboard.view', 'purchasing.view', 'purchasing.receive', 'inventory.view', 'inventory.manage', 'production.view', 'production.manage'],
            self::Sales => ['dashboard.view', 'sales.view', 'sales.manage', 'inventory.view', 'customers.view', 'customers.manage', 'receivables.view'],
            self::SuperAdministrator, self::Owner => ['*'],
        };
    }

    public function allows(string $permission): bool
    {
        return in_array('*', $this->permissions(), true)
            || in_array($permission, $this->permissions(), true);
    }

    /** @return list<self> */
    public function assignableRoles(): array
    {
        return match ($this) {
            self::SuperAdministrator, self::Owner => self::cases(),
            self::Admin => [self::Admin, self::Warehouse, self::Sales],
            default => [],
        };
    }

    public function canAssign(self $role): bool
    {
        return in_array($role, $this->assignableRoles(), true);
    }
}
