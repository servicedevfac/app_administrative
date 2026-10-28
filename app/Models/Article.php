<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    use HasFactory;

    public const STATUS_AVAILABLE = 'En stock';

    public const STATUS_LOW = 'Stock faible';

    public const STATUS_OUT = 'Rupture';

    protected $fillable = [
        'code',
        'name',
        'category',
        'unit',
        'quantity',
        'threshold',
        'location',
        'status',
        'is_available',
        'created_by',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'quantity' => 'integer',
        'threshold' => 'integer',
    ];

    public function requestItems()
    {
        return $this->hasMany(RequestItem::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isLowStock(): bool
    {
        return $this->quantity <= $this->threshold;
    }

    /**
     * @return array{quantity: int, status: string, is_available: bool}
     */
    public static function stockState(int $quantity, int $threshold): array
    {
        $status = match (true) {
            $quantity === 0 => self::STATUS_OUT,
            $quantity <= $threshold => self::STATUS_LOW,
            default => self::STATUS_AVAILABLE,
        };

        return [
            'quantity' => $quantity,
            'status' => $status,
            'is_available' => $quantity > 0,
        ];
    }
}
