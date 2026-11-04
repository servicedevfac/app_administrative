<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialRequest extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'Brouillon';

    public const STATUS_PENDING = 'En attente';

    public const STATUS_SUBMITTED = 'Soumise';

    public const STATUS_PENDING_VERIFICATION = 'En attente de vérification';

    public const STATUS_PENDING_VALIDATION = 'En attente de validation';

    public const STATUS_CORRECTION = 'À corriger';

    public const STATUS_VALIDATED = 'Validée';

    public const STATUS_APPROVED = 'Approuvée';

    public const STATUS_EXECUTING = "En cours d'exécution";

    public const STATUS_PENDING_SUPPLY = "En attente d'approvisionnement";

    public const STATUS_EXECUTED = 'Exécutée';

    public const STATUS_PENDING_JUSTIFICATION = 'En attente de justification';

    public const STATUS_REJECTED = 'Rejetée';

    public const STATUS_DELIVERED = 'Livrée';

    public const STATUS_RETURNED = 'Restituée';

    public const STATUS_CLOSED = 'Clôturée';

    public const STATUS_CANCELLED = 'Annulée';

    /**
     * @var list<string>
     */
    public const PENDING_WORKFLOW_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_SUBMITTED,
        self::STATUS_PENDING_VERIFICATION,
        self::STATUS_PENDING_VALIDATION,
    ];

    /**
     * @var list<string>
     */
    public const AUTHORIZED_STATUSES = [
        self::STATUS_VALIDATED,
        self::STATUS_APPROVED,
        self::STATUS_EXECUTING,
        'En cours d’exécution',
        self::STATUS_PENDING_SUPPLY,
        'En attente d’approvisionnement',
        self::STATUS_EXECUTED,
        self::STATUS_PENDING_JUSTIFICATION,
        self::STATUS_DELIVERED,
    ];

    /**
     * @var list<string>
     */
    public const TERMINAL_STATUSES = [
        self::STATUS_CLOSED,
        self::STATUS_CANCELLED,
        self::STATUS_REJECTED,
        self::STATUS_RETURNED,
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

    protected $appends = [
        'is_overdue',
        'days_overdue',
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

    public function getIsOverdueAttribute(): bool
    {
        if (in_array($this->status, array_merge(self::TERMINAL_STATUSES, [self::STATUS_DRAFT, self::STATUS_EXECUTED]), true)) {
            return false;
        }

        return $this->date_needed !== null && $this->date_needed->lt(today());
    }

    public function getDaysOverdueAttribute(): int
    {
        if (! $this->is_overdue || $this->date_needed === null) {
            return 0;
        }

        return (int) $this->date_needed->diffInDays(today());
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
