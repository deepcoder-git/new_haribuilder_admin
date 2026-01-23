<?php

declare(strict_types=1);

namespace App\Utility\Enums;

use App\Utility\Traits\CommonEnumTrait;
use EmreYarligan\EnumConcern\EnumConcern;

enum RoleEnum: string
{
    use CommonEnumTrait, EnumConcern;

    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case SiteSupervisor = 'site_supervisor';
    case WorkshopSiteManager = 'workshop_site_manager';
    case StoreManager = 'store_manager';
    case WorkshopStoreManager = 'workshop_store_manager';
    case TransportManager = 'transport_manager';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Admin',
            self::SiteSupervisor => 'Site Supervisor',
            self::WorkshopSiteManager => 'Workshop Site Manager',
            self::StoreManager => 'Store Manager',
            self::WorkshopStoreManager => 'Workshop Store Manager',
            self::TransportManager => 'Transport Manager',
        };
    }

    public function getPermissions(): array
    {
        return match ($this) {
            self::SuperAdmin => PermissionEnum::cases(),
            self::Admin => PermissionEnum::cases(),
            self::SiteSupervisor => [
                PermissionEnum::Dashboard,
                PermissionEnum::Orders,
            ],
            self::WorkshopSiteManager => [
                PermissionEnum::Dashboard,
                PermissionEnum::Orders,
            ],
            self::StoreManager => [
                PermissionEnum::Dashboard,
                PermissionEnum::Inventory,
                PermissionEnum::Orders,
                PermissionEnum::Wastage,
                PermissionEnum::Reports,
                PermissionEnum::Settings,
                PermissionEnum::Roles,
            ],
            self::WorkshopStoreManager => [
                PermissionEnum::Dashboard,
                PermissionEnum::Inventory,
                PermissionEnum::Orders,
                PermissionEnum::Wastage,
                PermissionEnum::Reports,
                PermissionEnum::Settings,
                PermissionEnum::Roles,
            ],
            self::TransportManager => [
                PermissionEnum::Dashboard,
                PermissionEnum::Orders,
                PermissionEnum::Delivery,
            ],
            default => [],
        };
    }

    public function canAccess(UserTypeEnum $userType): bool
    {
        return match ($this) {
            self::SuperAdmin, self::Admin, self::SiteSupervisor, self::WorkshopSiteManager,
            self::StoreManager, self::WorkshopStoreManager, self::TransportManager => $userType === UserTypeEnum::Moderator,
        };
    }

    public function getGuard(): string
    {
        return match ($this) {
            self::SuperAdmin => 'super_admin',
            self::Admin => 'admin',
            self::SiteSupervisor => 'site_supervisor',
            self::WorkshopSiteManager => 'workshop_site_manager',
            self::StoreManager => 'store_manager',
            self::WorkshopStoreManager => 'workshop_store_manager',
            self::TransportManager => 'transport_manager',
        };
    }

    public function getPasswordBroker(): string
    {
        return match ($this) {
            self::SuperAdmin => 'super_admin',
            self::Admin => 'admin',
            self::SiteSupervisor => 'site_supervisor',
            self::WorkshopSiteManager => 'workshop_site_manager',
            self::StoreManager => 'store_manager',
            self::WorkshopStoreManager => 'workshop_store_manager',
            self::TransportManager => 'transport_manager',
        };
    }
}
