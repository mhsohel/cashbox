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
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('recurring_schedule_id')->nullable()->constrained('recurring_schedules')->nullOnDelete();
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->foreignId('recurring_schedule_id')->nullable()->constrained('recurring_schedules')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['recurring_schedule_id']);
            $table->dropColumn('recurring_schedule_id');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->dropForeign(['recurring_schedule_id']);
            $table->dropColumn('recurring_schedule_id');
        });
    }
};
