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
        Schema::table('accounting_periods', function (Blueprint $table) {
            $table->foreignId('closed_by')->nullable()->change();
            $table->timestamp('closed_at')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('accounting_periods')->whereNull('closed_by')->delete();

        Schema::table('accounting_periods', function (Blueprint $table) {
            $table->foreignId('closed_by')->nullable(false)->change();
            $table->timestamp('closed_at')->nullable(false)->change();
        });
    }
};
