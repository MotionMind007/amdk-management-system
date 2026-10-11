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
        Schema::create('due_reminders', function (Blueprint $table) {
            $table->id();
            $table->date('reminder_date');
            $table->string('kind');
            $table->morphs('subject');
            $table->timestamps();
            $table->unique(['reminder_date', 'kind', 'subject_type', 'subject_id'], 'due_reminders_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('due_reminders');
    }
};
