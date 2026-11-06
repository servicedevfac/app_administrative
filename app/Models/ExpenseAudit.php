<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseAudit extends Model
{
    protected $fillable = [
        'expense_id',
        'user_id',
        'action',
        'before_state',
        'after_state',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'before_state' => 'array',
            'after_state' => 'array',
        ];
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
