<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryItem extends Model
{
    protected $fillable = ['inventory_session_id', 'article_id', 'theoretical_quantity', 'physical_quantity', 'variance', 'justification'];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
