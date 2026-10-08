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
        Schema::table('sales', function (Blueprint $table) {
            $table->string('payment_type', 20)->default('credit')->index();
            $table->foreignId('cash_account_id')->nullable()->constrained()->restrictOnDelete();
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('payment_type', 20)->default('credit')->index();
            $table->date('due_date')->nullable()->index();
            $table->foreignId('cash_account_id')->nullable()->constrained()->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_account_id');
            $table->dropColumn('payment_type');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_account_id');
            $table->dropColumn(['payment_type', 'due_date']);
        });
    }
};
