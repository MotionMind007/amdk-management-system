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
            $table->string('delivery_status')->default('not_ready')->after('status')->index();
        });

        Schema::table('customer_payments', function (Blueprint $table) {
            $table->string('proof_path')->nullable()->after('notes');
        });

        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->foreignId('supplier_invoice_id')->nullable()->after('purchase_order_id')->constrained()->restrictOnDelete();
            $table->string('proof_path')->nullable()->after('notes');
        });

        DB::table('sales')
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->update(['delivery_status' => 'complete']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_invoice_id');
            $table->dropColumn('proof_path');
        });

        Schema::table('customer_payments', function (Blueprint $table) {
            $table->dropColumn('proof_path');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('delivery_status');
        });
    }
};
