<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Expense extends Model
{
    protected $fillable = [
        'reference',
        'material_request_id',
        'authorization_reference',
        'supplier_id',
        'beneficiary',
        'estimated_amount',
        'authorized_amount',
        'committed_amount',
        'disbursed_amount',
        'justified_amount',
        'payment_method',
        'status',
        'notes',
        'overrun_reason',
        'created_by',
        'validated_by',
        'validated_at',
    ];

    protected $appends = [
        'remaining_amount',
        'amount_to_justify',
        'requires_reapproval',
    ];

    protected function casts(): array
    {
        return [
            'estimated_amount' => 'decimal:2',
            'authorized_amount' => 'decimal:2',
            'committed_amount' => 'decimal:2',
            'disbursed_amount' => 'decimal:2',
            'justified_amount' => 'decimal:2',
            'validated_at' => 'datetime',
        ];
    }

    public function materialRequest(): BelongsTo
    {
        return $this->belongsTo(MaterialRequest::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ExpenseAttachment::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(ExpenseAudit::class);
    }

    public function getRemainingAmountAttribute(): float
    {
        return (float) $this->authorized_amount - (float) $this->disbursed_amount;
    }

    public function getAmountToJustifyAttribute(): float
    {
        return max(0, (float) $this->disbursed_amount - (float) $this->justified_amount);
    }

    public function getRequiresReapprovalAttribute(): bool
    {
        return (float) $this->committed_amount > (float) $this->authorized_amount
            || (float) $this->disbursed_amount > (float) $this->authorized_amount;
    }
}
