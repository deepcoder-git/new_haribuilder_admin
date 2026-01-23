<?php

declare(strict_types=1);

namespace App\Utility\Enums;

use App\Utility\Traits\CommonEnumTrait;
use EmreYarligan\EnumConcern\EnumConcern;

enum UserTypeEnum: string
{
    use CommonEnumTrait, EnumConcern;

    case Moderator = 'moderator';
    case Staff = 'staff';
    case Parent = 'parent';
    case Student = 'student';

    public function getGuard(): string
    {
        return match ($this) {
            self::Moderator => 'moderator',
            self::Staff => 'staff',
            self::Parent => 'parent',
            self::Student => 'student',
        };
    }

    public function getPasswordBroker(): string
    {
        return match ($this) {
            self::Moderator => 'moderators',
            self::Staff => 'staffs',
            self::Parent => 'parents',
            self::Student => 'students',
        };
    }
}

