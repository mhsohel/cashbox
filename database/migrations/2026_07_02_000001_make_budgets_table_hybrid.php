<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Drop the unique constraint ['user_id', 'category_id'] from previous perpetual budgets step
        Schema::table('budgets', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'category_id']);
        });

        // 2. Add back the month column as nullable
        Schema::table('budgets', function (Blueprint $table) {
            $table->string('month')->nullable();
        });

        // 3. Create partial unique indexes to support hybrid budgets:
        // - Perpetual budgets (where month is NULL) must be unique per user & category
        // - Monthly budgets (where month is NOT NULL) must be unique per user, category & month
        DB::statement('CREATE UNIQUE INDEX budgets_user_id_category_id_perpetual_unique ON budgets (user_id, category_id) WHERE month IS NULL');
        DB::statement('CREATE UNIQUE INDEX budgets_user_id_category_id_month_unique ON budgets (user_id, category_id, month) WHERE month IS NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the partial indexes
        DB::statement('DROP INDEX IF EXISTS budgets_user_id_category_id_perpetual_unique');
        DB::statement('DROP INDEX IF EXISTS budgets_user_id_category_id_month_unique');

        // Drop the month column and restore the perpetual unique constraint
        Schema::table('budgets', function (Blueprint $table) {
            $table->dropColumn('month');
            $table->unique(['user_id', 'category_id']);
        });
    }
};
