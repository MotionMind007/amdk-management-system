<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->index(['warehouse_id', 'sale_date']);
            $table->index(['outstanding_amount', 'due_date']);
        });
        Schema::table('supplier_invoices', function (Blueprint $table): void {
            $table->index(['outstanding_amount', 'due_date']);
        });
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->index(['warehouse_id', 'occurred_at']);
        });
        Schema::table('daily_productions', function (Blueprint $table): void {
            $table->index(['warehouse_id', 'production_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropIndex(['warehouse_id', 'sale_date']);
            $table->dropIndex(['outstanding_amount', 'due_date']);
        });
        Schema::table('supplier_invoices', function (Blueprint $table): void {
            $table->dropIndex(['outstanding_amount', 'due_date']);
        });
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropIndex(['warehouse_id', 'occurred_at']);
        });
        Schema::table('daily_productions', function (Blueprint $table): void {
            $table->dropIndex(['warehouse_id', 'production_date']);
        });
    }
};
