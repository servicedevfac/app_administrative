<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_COLLABORATOR = 'collaborator';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'service',
        'matricule',
        'avatar',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isCollaborator(): bool
    {
        return $this->role === self::ROLE_COLLABORATOR;
    }

    public static function normalizeRole(?string $role): string
    {
        return match ($role) {
            'admin', 'Administrateur', 'manager', 'Responsable' => self::ROLE_ADMIN,
            default => self::ROLE_COLLABORATOR,
        };
    }

    public static function defaultAvatar(string $role): string
    {
        return $role === self::ROLE_ADMIN
            ? 'assets/avatars/jean.svg'
            : 'assets/avatars/aminata.svg';
    }

    public function materialRequests()
    {
        return $this->hasMany(MaterialRequest::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function documents()
    {
        return $this->hasMany(AdministrativeDocument::class);
    }
}
