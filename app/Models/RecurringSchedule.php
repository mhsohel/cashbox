<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringSchedule extends Model
{
    protected $fillable = [
        'user_id',
        'type', // 'expense', 'loan_installment', 'loan'
        'frequency', // 'weekly', 'monthly', 'quarterly'
        'start_date',
        'next_due_date',
        'last_run_date',
        'is_active',
        'amount',
        'description',
        'account_id',
        'category_id',
        'loan_id',
        'loan_type',
        'person_name',
    ];

    protected $casts = [
        'start_date' => 'date',
        'next_due_date' => 'date',
        'last_run_date' => 'date',
        'is_active' => 'boolean',
        'amount' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function generatedLoans(): HasMany
    {
        return $this->hasMany(Loan::class, 'recurring_schedule_id');
    }
}
