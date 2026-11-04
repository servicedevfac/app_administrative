<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Vehicle extends Model
{
    protected $fillable = [
        'registration_number',
        'brand',
        'model',
        'type',
        'registration_card_number',
        'mileage',
        'department',
        'driver_id',
        'status',
        'insurance_expires_at',
        'inspection_expires_at',
        'tax_expires_at',
        'next_maintenance_at',
        'next_maintenance_mileage',
        'notes',
    ];

    protected $appends = [
        'alerts_summary',
        'has_alert',
    ];

    protected function casts(): array
    {
        return [
            'mileage' => 'integer',
            'next_maintenance_mileage' => 'integer',
            'insurance_expires_at' => 'date',
            'inspection_expires_at' => 'date',
            'tax_expires_at' => 'date',
            'next_maintenance_at' => 'date',
        ];
    }

    /**
     * @return list<string>
     */
    public function getAlertsSummaryAttribute(): array
    {
        $alerts = [];
        $horizon = today()->addDays(30);

        if ($this->insurance_expires_at && $this->insurance_expires_at->lte($horizon)) {
            $alerts[] = $this->insurance_expires_at->lt(today()) ? 'Assurance expirée' : 'Assurance bientôt à échéance';
        }
        if ($this->inspection_expires_at && $this->inspection_expires_at->lte($horizon)) {
            $alerts[] = $this->inspection_expires_at->lt(today()) ? 'Visite technique expirée' : 'Visite technique bientôt à échéance';
        }
        if ($this->tax_expires_at && $this->tax_expires_at->lte($horizon)) {
            $alerts[] = $this->tax_expires_at->lt(today()) ? 'Vignette expirée' : 'Vignette bientôt à échéance';
        }
        if ($this->next_maintenance_at && $this->next_maintenance_at->lte($horizon)) {
            $alerts[] = $this->next_maintenance_at->lt(today()) ? 'Entretien en retard (date)' : 'Entretien proche (date)';
        }
        if ($this->next_maintenance_mileage !== null && $this->mileage >= ($this->next_maintenance_mileage - 500)) {
            $alerts[] = $this->mileage >= $this->next_maintenance_mileage
                ? "Seuil km entretien dépassé ({$this->mileage} / {$this->next_maintenance_mileage} km)"
                : "Entretien km imminent ({$this->mileage} / {$this->next_maintenance_mileage} km)";
        }

        return $alerts;
    }

    public function getHasAlertAttribute(): bool
    {
        return count($this->alerts_summary) > 0;
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(VehicleDocument::class);
    }

    public function maintenanceRecords(): MorphMany
    {
        return $this->morphMany(MaintenanceRecord::class, 'maintainable');
    }
}
