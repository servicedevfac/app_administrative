<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedReport extends Model
{
    protected $fillable = [
        'reference',
        'type',
        'title',
        'period_from',
        'period_to',
        'difficulties',
        'action_items',
        'filters',
        'snapshot',
        'generated_by',
    ];

    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'filters' => 'array',
            'snapshot' => 'array',
        ];
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
