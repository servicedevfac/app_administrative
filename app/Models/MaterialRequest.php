<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialRequest extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'Brouillon';

    public const STATUS_PENDING = 'En attente';

    public const STATUS_CORRECTION = 'À corriger';

    public const STATUS_APPROVED = 'Approuvée';

    public const STATUS_REJECTED = 'Rejetée';

    public const STATUS_DELIVERED = 'Livrée';

    public const STATUS_RETURNED = 'Restituée';

    public const STATUS_CLOSED = 'Clôturée';

    public const STATUS_CANCELLED = 'Annulée';

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
        'correction_reason',
        'approved_by',
        'approved_at',
        'submitted_at',
        'delivered_at',
        'closed_at',
    ];

    protected $casts = [
        'date_needed' => 'date',
        'date_return' => 'date',
        'approved_at' => 'datetime',
        'submitted_at' => 'datetime',
        'delivered_at' => 'datetime',
        'closed_at' => 'datetime',
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

    public function decisions(): HasMany
    {
        return $this->hasMany(RequestDecision::class);
    }
}
