<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_COLLABORATOR = 'collaborator';

    public const ROLE_MANAGER = 'manager';

    /**
     * @var list<string>
     */
    public const ALL_PERMISSIONS = [
        'requests.create',
        'requests.verify',
        'requests.validate',
        'requests.assign',
        'requests.execute',
        'stock.view',
        'stock.manage',
        'inventory.manage',
        'inventory.validate',
        'assets.manage',
        'vehicles.manage',
        'expenses.manage',
        'expenses.validate',
        'reports.view',
        'documents.manage',
        'users.manage',
        'settings.manage',
        'audit.view',
        'backups.manage',
    ];

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'permissions',
        'service',
        'phone',
        'office_location',
        'matricule',
        'avatar',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'api_token_hash',
    ];

    protected $appends = [
        'effective_permissions',
        'has_active_delegation',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'is_active' => 'boolean',
            'permissions' => 'array',
        ];
    }

    /**
     * @return list<string>
     */
    public static function defaultPermissionsForRole(string $role): array
    {
        return match ($role) {
            self::ROLE_ADMIN => self::ALL_PERMISSIONS,
            self::ROLE_MANAGER => [
                'requests.create',
                'requests.verify',
                'requests.validate',
                'requests.assign',
                'requests.execute',
                'stock.view',
                'stock.manage',
                'inventory.manage',
                'inventory.validate',
                'assets.manage',
                'vehicles.manage',
                'expenses.manage',
                'expenses.validate',
                'reports.view',
                'documents.manage',
            ],
            default => [
                'requests.create',
                'stock.view',
            ],
        };
    }

    /**
     * @return list<string>
     */
    public function getEffectivePermissionsAttribute(): array
    {
        if ($this->isAdmin()) {
            return self::ALL_PERMISSIONS;
        }

        $base = is_array($this->permissions)
            ? array_values(array_intersect(self::ALL_PERMISSIONS, $this->permissions))
            : self::defaultPermissionsForRole($this->role ?? self::ROLE_COLLABORATOR);

        if ($this->hasActiveDelegation()) {
            $base = array_values(array_unique(array_merge($base, ['requests.verify', 'requests.validate'])));
        }

        return $base;
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return in_array($permission, $this->effective_permissions, true);
    }

    public function getHasActiveDelegationAttribute(): bool
    {
        return $this->hasActiveDelegation();
    }

    public function hasActiveDelegation(?string $department = null): bool
    {
        if (! $this->exists || ! Schema::hasTable('approval_delegations')) {
            return false;
        }

        $today = now()->toDateString();

        return ApprovalDelegation::query()
            ->where('delegate_id', $this->id)
            ->where('is_active', true)
            ->whereDate('starts_at', '<=', $today)
            ->where(function ($query) use ($today): void {
                $query->whereNull('ends_at')->orWhereDate('ends_at', '>=', $today);
            })
            ->when($department !== null, function ($query) use ($department): void {
                $query->where(function ($sub) use ($department): void {
                    $sub->where('scope', '!=', 'Service spécifique')
                        ->orWhereNull('department')
                        ->orWhere('department', $department);
                });
            })
            ->exists();
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isCollaborator(): bool
    {
        return $this->role === self::ROLE_COLLABORATOR;
    }

    public function isManager(): bool
    {
        return $this->role === self::ROLE_MANAGER;
    }

    public function canManageOperations(): bool
    {
        return $this->isAdmin() || $this->isManager() || $this->hasActiveDelegation();
    }

    public static function normalizeRole(?string $role): string
    {
        return match ($role) {
            'admin', 'Administrateur' => self::ROLE_ADMIN,
            'manager', 'Responsable' => self::ROLE_MANAGER,
            default => self::ROLE_COLLABORATOR,
        };
    }

    public static function defaultAvatar(string $role): string
    {
        return in_array($role, [self::ROLE_ADMIN, self::ROLE_MANAGER], true)
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

    public function materialRequests(): HasMany
    {
        return $this->hasMany(MaterialRequest::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(AdministrativeDocument::class);
    }

    public function delegationsReceived(): HasMany
    {
        return $this->hasMany(ApprovalDelegation::class, 'delegate_id');
    }
}
