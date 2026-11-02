<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdministrativeDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_request_id',
        'title',
        'category',
        'type',
        'service',
        'file_path',
        'original_name',
        'mime_type',
        'file_size_bytes',
        'file_size',
        'reference',
        'user_id',
    ];

    protected function casts(): array
    {
        return ['file_size_bytes' => 'integer'];
    }

    public function materialRequest()
    {
        return $this->belongsTo(MaterialRequest::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
