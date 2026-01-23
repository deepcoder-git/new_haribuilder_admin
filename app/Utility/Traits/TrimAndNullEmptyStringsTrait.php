<?php

declare(strict_types=1);

namespace App\Utility\Traits;

trait TrimAndNullEmptyStringsTrait
{
    public array $convertEmptyStringsExcept = [];

    public function updatedTrimAndNullEmptyStringsTrait($name, $value): void
    {
        if (is_string($value) && ! in_array($name, $this->convertEmptyStringsExcept)) {
            $value = trim($value);
            $value = $value === '' ? null : $value;
            data_set($this, $name, $value);
        }
    }
}
