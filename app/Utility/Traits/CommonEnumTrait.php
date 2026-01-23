<?php

declare(strict_types=1);

namespace App\Utility\Traits;

trait CommonEnumTrait
{
    public static function inRule(): string
    {
        return self::all()->values()->implode(',');
    }

    public function getName(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
