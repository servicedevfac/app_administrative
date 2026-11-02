<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

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
        'api_token_hash',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
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

    public function issueApiToken(): string
    {
        $token = Str::random(80);

        $this->forceFill([
            'api_token_hash' => hash('sha256', $token),
            'last_login_at' => now(),
        ])->save();

        return $token;
    }

    public function revokeApiToken(): void
    {
        $this->forceFill(['api_token_hash' => null])->save();
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
