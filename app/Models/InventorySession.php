<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventorySession extends Model
{
    protected $fillable = ['reference', 'scope', 'location', 'status', 'inventory_date', 'notes', 'created_by', 'validated_by', 'validated_at'];

    protected function casts(): array
    {
        return ['inventory_date' => 'date', 'validated_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}
