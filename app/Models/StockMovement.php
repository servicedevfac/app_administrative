<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory;

    public const SOURCE_INITIAL_STOCK = 'initial_stock';

    public const SOURCE_MANUAL_ADJUSTMENT = 'manual_adjustment';

    public const SOURCE_REQUEST_APPROVAL = 'request_approval';

    public const SOURCE_REQUEST_RETURN = 'request_return';

    protected $fillable = [
        'article_id',
        'user_id',
        'material_request_id',
        'type',
        'source',
        'quantity',
        'reason',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function materialRequest(): BelongsTo
    {
        return $this->belongsTo(MaterialRequest::class);
    }
}
