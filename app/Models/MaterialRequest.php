<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialRequest extends Model
{
    use HasFactory;

    public const STATUS_SUBMITTED = 'Soumise';

    public const STATUS_PENDING_VALIDATION = 'En attente de validation';

    public const STATUS_VALIDATED = 'Validée';

    public const STATUS_REJECTED = 'Rejetée';

    /**
     * @var list<string>
     */
    public const STATUSES = [
        self::STATUS_SUBMITTED,
        self::STATUS_PENDING_VALIDATION,
        self::STATUS_REJECTED,
        self::STATUS_VALIDATED,
    ];

    /**
     * @var list<string>
     */
    public const PENDING_WORKFLOW_STATUSES = [
        self::STATUS_SUBMITTED,
        self::STATUS_PENDING_VALIDATION,
    ];

    /**
     * @var list<string>
     */
    public const AUTHORIZED_STATUSES = [
        self::STATUS_VALIDATED,
    ];

    /**
     * @var list<string>
     */
    public const TERMINAL_STATUSES = [
        self::STATUS_REJECTED,
        self::STATUS_VALIDATED,
    ];

    protected $fillable = [
        'reference',
        'user_id',
        'verified_by',
        'type_operation',
        'category',
        'motif',
        'service_description',
        'service',
        'date_needed',
        'date_return',
        'urgency',
        'status',
        'notes',
        'rejection_reason',
        'correction_reason',
        'approved_by',
        'assigned_to',
        'approved_at',
        'submitted_at',
        'verified_at',
        'executed_at',
        'delivered_at',
        'received_at',
        'closed_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'date_needed' => 'date',
        'date_return' => 'date',
        'approved_at' => 'datetime',
        'submitted_at' => 'datetime',
        'verified_at' => 'datetime',
        'executed_at' => 'datetime',
        'delivered_at' => 'datetime',
        'received_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $materialRequest): void {
            if (! in_array($materialRequest->status, self::STATUSES, true)) {
                throw new \InvalidArgumentException("Statut de demande invalide : {$materialRequest->status}");
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RequestItem::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(AdministrativeDocument::class);
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(RequestDecision::class);
    }
}
