<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MaintenanceRecord extends Model
{
    protected $fillable = ['maintainable_type', 'maintainable_id', 'type', 'provider', 'scheduled_at', 'completed_at', 'mileage', 'cost', 'status', 'description', 'recorded_by'];

    protected function casts(): array
    {
        return ['scheduled_at' => 'date', 'completed_at' => 'date', 'cost' => 'decimal:2'];
    }

    public function maintainable(): MorphTo
    {
        return $this->morphTo();
    }
}
