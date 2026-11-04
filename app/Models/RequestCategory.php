<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestCategory extends Model
{
    protected $fillable = [
        'name',
        'description',
        'requires_stock_items',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'requires_stock_items' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}

