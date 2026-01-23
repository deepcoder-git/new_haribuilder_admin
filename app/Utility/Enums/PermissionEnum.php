<?php

declare(strict_types=1);

namespace App\Utility\Enums;

use App\Utility\Traits\CommonEnumTrait;
use EmreYarligan\EnumConcern\EnumConcern;

enum PermissionEnum: string
{
    use CommonEnumTrait, EnumConcern;

    case Dashboard = 'dashboard';
    case Users = 'users';
    case Orders = 'orders';
    case Inventory = 'inventory';
    case Wastage = 'wastage';
    case Reports = 'reports';
    case Settings = 'settings';
    case Roles = 'roles';
    case Permissions = 'permissions';
    case Delivery = 'delivery';

    public static function getPermissionGroup(string $group): array
    {
        $groups = [
            'dashboard' => [self::Dashboard->value],
            'users' => [self::Users->value],
            'orders' => [self::Orders->value],
            'inventory' => [self::Inventory->value],
            'wastage' => [self::Wastage->value],
            'reports' => [self::Reports->value],
            'settings' => [self::Settings->value],
            'roles' => [self::Roles->value],
            'permissions' => [self::Permissions->value],
            'delivery' => [self::Delivery->value],
        ];

        return $groups[$group] ?? [];
    }
}

