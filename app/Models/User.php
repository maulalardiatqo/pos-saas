<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants; // Tambahan untuk Multi-Tenancy
use Filament\Panel;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model; // Tambahan untuk parameter canAccessTenant
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection; 
use Laravel\Sanctum\HasApiTokens;

// 1. TAMBAHKAN implements FilamentUser dan HasTenants
class User extends Authenticatable implements FilamentUser, HasTenants
{
    use HasApiTokens;
    use HasFactory;
    use HasUlids;
    use Notifiable;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = [
        'password',
        'remember_token',
        'pin',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    public function isPlatform(): bool
    {
        return $this->user_type === 'platform';
    }

    public function isTenant(): bool
    {
        return $this->user_type === 'tenant';
    }

    public function isOwner(): bool
    {
        return $this->role?->code === 'owner';
    }

    public function isCashier(): bool
    {
        return $this->role?->code === 'cashier';
    }

    /*
    |--------------------------------------------------------------------------
    | Filament Access & Permissions (RBAC)
    |--------------------------------------------------------------------------
    */

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'admin') {
            return $this->isPlatform();
        }

        if ($panel->getId() === 'tenant') {
            return $this->isTenant();
        }

        return false;
    }

    public function hasPermission(string $permissionCode): bool
    {
        if ($this->isPlatform()) {
            return true;
        }

        if ($this->isOwner()) {
            return true;
        }

        return $this->role?->permissions->contains('code', $permissionCode) ?? false;
    }

    /*
    |--------------------------------------------------------------------------
    | Filament Multi-Tenancy Methods
    |--------------------------------------------------------------------------
    */

    public function getTenants(Panel $panel): array|Collection
    {
        if ($this->isTenant() && $this->company) {
            return [$this->company];
        }

        return [];
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->company_id === $tenant->id;
    }
}