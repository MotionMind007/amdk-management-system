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
        Schema::table('sales', function (Blueprint $table) {
            $table->string('payment_method', 20)->nullable()->after('payment_type')->index();
            $table->string('sender_bank', 100)->nullable()->after('payment_method');
        });

        DB::table('sales')
            ->where('payment_type', 'cash')
            ->update(['payment_method' => 'cash']);

        $bankAccountIds = DB::table('cash_accounts')
            ->where('type', 'bank')
            ->pluck('id');

        if ($bankAccountIds->isNotEmpty()) {
            DB::table('sales')
                ->where('payment_type', 'cash')
                ->whereIn('cash_account_id', $bankAccountIds)
                ->update(['payment_method' => 'transfer']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'sender_bank']);
        });
    }
};
