<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->unique()
                ->after('id')
                ->constrained()
                ->nullOnDelete();
        });

        $employees = DB::table('employees')
            ->whereNotNull('email')
            ->orderBy('id')
            ->get(['id', 'email']);

        foreach ($employees as $employee) {
            $userId = DB::table('users')->where('email', $employee->email)->value('id');

            if ($userId === null || DB::table('employees')->where('user_id', $userId)->exists()) {
                continue;
            }

            DB::table('employees')->where('id', $employee->id)->update(['user_id' => $userId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
