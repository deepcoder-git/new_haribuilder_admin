<?php

declare(strict_types=1);

namespace App\Utility\Enums;

use App\Utility\Traits\CommonEnumTrait;
use EmreYarligan\EnumConcern\EnumConcern;

enum WhatsappSessionStatusEnum: string
{
    use CommonEnumTrait, EnumConcern;

    case Connected = 'connected';

    case Connecting = 'connecting';

    case Offline = 'offline';

    public function getBadge(): string
    {
        $string = match ($this) {
            self::Connected => 'bg-success text-white',
            self::Connecting => 'bg-warning text-black',
            self::Offline => 'bg-danger text-white',
            default          => 'bg-info text-white',
        };

        return vsprintf('<span class="badge %s">%s</span>', [$string, $this->getName()]);
    }
    public static function getBadgeByValue(?string $value): string
    {
        try {
            return self::from($value)->getBadge();
        } catch (\ValueError $e) {
            return '<span class="badge bg-info text-white">' . $value . '</span>';
        }
    }
}
