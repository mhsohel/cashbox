<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class License extends Model
{
    protected $fillable = [
        'user_id',
        'client_id',
        'amount',
        'billing_cycle',
        'next_renewal_date',
        'status',
        'last_paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'next_renewal_date' => 'date',
        'last_paid_at' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
