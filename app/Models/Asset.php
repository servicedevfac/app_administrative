<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Asset extends Model
{
    protected $fillable = [
        'inventory_number',
        'name',
        'category',
        'serial_number',
        'acquired_at',
        'acquisition_cost',
        'location',
        'department',
        'assigned_user_id',
        'condition',
        'availability',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'acquired_at' => 'date',
            'acquisition_cost' => 'decimal:2',
        ];
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(AssetIncident::class);
    }

    public function maintenanceRecords(): MorphMany
    {
        return $this->morphMany(MaintenanceRecord::class, 'maintainable');
    }
}
