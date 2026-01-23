<?php

declare(strict_types=1);

namespace App\Models;

use App\Utility\Enums\PermissionEnum;
use App\Utility\Enums\RoleEnum;
use App\Utility\Enums\SchoolBoardEnum;
use App\Utility\Enums\UserTypeEnum;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Moderator extends Authenticatable implements HasMedia
{
    use HasFactory, InteractsWithMedia, Notifiable, HasApiTokens;

    protected $casts = [
        'board' => SchoolBoardEnum::class,
        'role' => RoleEnum::class,
        'permissions' => 'array',
    ];

    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'board',
        'role',
        'image',
        'permissions',
        'mobile_number',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('profile_image')->singleFile()->useFallbackUrl(mix('build/panel/images/user.png')->toHtml());
    }

    protected function password(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => bcrypt($value),
        );
    }

    /**
     * Get user type
     */
    public function getUserType(): UserTypeEnum
    {
        return UserTypeEnum::Moderator;
    }

    /**
     * Get user role
     */
    public function getRole(): ?RoleEnum
    {
        if (!$this->role) {
            return null;
        }

        if ($this->role instanceof RoleEnum) {
            return $this->role;
        }

        return RoleEnum::tryFrom((string) $this->role);
    }

    /**
     * Check if user has a specific role
     */
    public function hasRole(RoleEnum $role): bool
    {
        return $this->getRole() === $role;
    }

    /**
     * Check if user has any of the given roles
     */
    public function hasAnyRole(array $roles): bool
    {
        $userRole = $this->getRole();
        if (!$userRole) {
            return false;
        }

        foreach ($roles as $role) {
            if ($userRole === $role) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get user permissions
     */
    public function getPermissions(): array
    {
        $role = $this->getRole();
        
        if ($role && $role === RoleEnum::SuperAdmin) {
            return PermissionEnum::cases();
        }

        if ($this->permissions && is_array($this->permissions)) {
            return array_map(fn ($perm) => PermissionEnum::tryFrom($perm), array_filter($this->permissions));
        }

        if ($role) {
            return $role->getPermissions();
        }

        return [];
    }

    /**
     * Check if user has a specific permission
     */
    public function hasPermission(PermissionEnum $permission): bool
    {
        if ($this->hasRole(RoleEnum::SuperAdmin)) {
            return true;
        }
        
        $permissions = $this->getPermissions();
        return in_array($permission, $permissions, true);
    }

    /**
     * Check if user has any of the given permissions
     */
    public function hasAnyPermission(array $permissions): bool
    {
        if ($this->hasRole(RoleEnum::SuperAdmin)) {
            return true;
        }
        
        $userPermissions = $this->getPermissions();
        foreach ($permissions as $permission) {
            if (in_array($permission, $userPermissions, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has all of the given permissions
     */
    public function hasAllPermissions(array $permissions): bool
    {
        if ($this->hasRole(RoleEnum::SuperAdmin)) {
            return true;
        }
        
        $userPermissions = $this->getPermissions();
        foreach ($permissions as $permission) {
            if (!in_array($permission, $userPermissions, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Assign role to user
     */
    public function assignRole(RoleEnum $role): void
    {
        $this->update(['role' => $role->value]);
    }

    /**
     * Assign permissions to user
     */
    public function assignPermissions(array $permissions): void
    {
        $permissionValues = array_map(fn ($perm) => $perm instanceof PermissionEnum ? $perm->value : $perm, $permissions);
        $this->update(['permissions' => $permissionValues]);
    }
}
