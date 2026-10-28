<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaterialRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'user_id',
        'type_operation',
        'motif',
        'service',
        'date_needed',
        'date_return',
        'urgency',
        'status',
        'notes',
        'rejection_reason',
        'approved_by',
        'approved_at',
        'delivered_at',
    ];

    protected $casts = [
        'date_needed' => 'date',
        'date_return' => 'date',
        'approved_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items()
    {
        return $this->hasMany(RequestItem::class);
    }

    public function documents()
    {
        return $this->hasMany(AdministrativeDocument::class);
    }
}
