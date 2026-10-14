<?php

namespace App\Services;

use App\Models\User;
use App\UserRole;
use Illuminate\Validation\ValidationException;

class PrivilegedAccountGuard
{
    public function ensureCanChange(User $user, UserRole|string $newRole, bool $willBeActive, string $activeField = 'is_active'): void
    {
        $newRole = is_string($newRole) ? UserRole::from($newRole) : $newRole;

        if (! $user->is_active || ! $this->isPrivileged($user->role)) {
            return;
        }

        if ($willBeActive && $this->isPrivileged($newRole)) {
            return;
        }

        $hasOtherPrivilegedAccount = User::query()
            ->whereKeyNot($user->getKey())
            ->where('is_active', true)
            ->whereIn('role', [UserRole::Owner->value, UserRole::SuperAdministrator->value])
            ->lockForUpdate()
            ->exists();

        if ($hasOtherPrivilegedAccount) {
            return;
        }

        $field = $willBeActive ? 'role' : $activeField;

        throw ValidationException::withMessages([
            $field => 'Minimal satu akun Owner atau Super Admin harus tetap aktif.',
        ]);
    }

    private function isPrivileged(UserRole $role): bool
    {
        return in_array($role, [UserRole::Owner, UserRole::SuperAdministrator], true);
    }
}
