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
        Schema::create('recurring_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // 'expense', 'loan_installment', 'loan'
            $table->string('frequency'); // 'weekly', 'monthly', 'quarterly'
            $table->date('start_date');
            $table->date('next_due_date');
            $table->date('last_run_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->decimal('amount', 15, 2);
            $table->text('description')->nullable();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('loan_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('loan_type')->nullable(); // 'lent', 'borrowed' (used when type is 'loan')
            $table->string('person_name')->nullable(); // (used when type is 'loan')
            $table->timestamps();

            $table->index(['user_id', 'next_due_date', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_schedules');
    }
};
