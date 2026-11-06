<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetIncident extends Model
{
    protected $fillable = [
        'reference',
        'asset_id',
        'reported_by',
        'severity',
        'title',
        'description',
        'status',
        'incident_date',
        'resolved_at',
        'resolution_notes',
    ];

    protected function casts(): array
    {
        return [
            'incident_date' => 'date',
            'resolved_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
