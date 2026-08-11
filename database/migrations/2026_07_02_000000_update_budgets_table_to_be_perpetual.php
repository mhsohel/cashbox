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
        // 1. Clean up duplicate budget records before changing constraints
        $duplicates = DB::table('budgets')
            ->select('user_id', 'category_id', DB::raw('MAX(id) as max_id'))
            ->groupBy('user_id', 'category_id')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('budgets')
                ->where('user_id', $duplicate->user_id)
                ->where('category_id', $duplicate->category_id)
                ->where('id', '!=', $duplicate->max_id)
                ->delete();
        }

        // 2. Modify the budgets table schema
        Schema::table('budgets', function (Blueprint $table) {
            // Drop indexes and constraints
            $table->dropUnique(['user_id', 'category_id', 'month']);
            $table->dropIndex(['user_id', 'month']);
            
            // Drop the month column
            $table->dropColumn('month');
            
            // Add new unique constraint
            $table->unique(['user_id', 'category_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            // Re-add month column
            $table->string('month')->nullable();
            
            // Drop the new unique constraint
            $table->dropUnique(['user_id', 'category_id']);
            
            // Restore previous constraints
            $table->unique(['user_id', 'category_id', 'month']);
            $table->index(['user_id', 'month']);
        });
    }
};
