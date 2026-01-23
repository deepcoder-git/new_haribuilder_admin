<?php

declare(strict_types=1);

namespace App\Utility\Enums;

use App\Utility\Traits\CommonEnumTrait;
use EmreYarligan\EnumConcern\EnumConcern;

enum StaffRoleEnum: string
{
    use CommonEnumTrait, EnumConcern {
        getName as getLabel;
    }

    case Teacher = 'teacher';
    case Librarian = 'librarian';
    case Accountant = 'accountant';
    case Principal = 'principal';
    case MessManager = 'mess_manager';
    case NonTeachingStaff = 'non_teaching_staff';
    case InventoryManager = 'inventory_manager';
    case HostelManager = 'hostel_manager';
    case Visitor = 'visitor';

    public function getName(): string
    {
        return match ($this) {
            self::Visitor => 'Visitor(Gate Keeper)',
            default => $this->getLabel(),//@phpstan-ignore-line
        };
    }
}
