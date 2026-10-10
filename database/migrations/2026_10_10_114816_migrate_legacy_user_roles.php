<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $roleMapping = [
            'administrator' => 'super_admin',
            'manager' => 'owner',
            'finance' => 'admin',
            'purchasing' => 'admin',
            'production' => 'warehouse',
        ];

        foreach ($roleMapping as $legacyRole => $newRole) {
            DB::table('users')->where('role', $legacyRole)->update(['role' => $newRole]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $roleMapping = [
            'super_admin' => 'administrator',
            'owner' => 'manager',
            'admin' => 'purchasing',
        ];

        foreach ($roleMapping as $newRole => $legacyRole) {
            DB::table('users')->where('role', $newRole)->update(['role' => $legacyRole]);
        }
    }
};
