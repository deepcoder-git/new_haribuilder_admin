<?php

declare(strict_types=1);

namespace App\Utility\Traits;

trait ConvertsDecimalToInteger
{
    /**
     * Convert a decimal value to integer
     * 
     * @param mixed $value The value to convert (can be string, float, int, etc.)
     * @return int The integer value (minimum 0)
     */
    protected function convertToInteger($value): int
    {
        // Convert to integer (removes decimals)
        $integerValue = (int)(float)$value;
        // Ensure minimum is 0
        return max(0, $integerValue);
    }

    /**
     * Convert a decimal value to integer string
     * 
     * @param mixed $value The value to convert
     * @return string The integer value as string
     */
    protected function convertToIntegerString($value): string
    {
        return (string)$this->convertToInteger($value);
    }

    /**
     * Convert array field value to integer
     * Useful for Livewire array properties like materials.0.quantity
     * 
     * @param array $array The array to modify
     * @param string $keyPath The key path (e.g., "0.quantity" or "materials.0.quantity")
     * @return void
     */
    protected function convertArrayFieldToInteger(array &$array, string $keyPath): void
    {
        $parts = explode('.', $keyPath);
        $lastKey = array_pop($parts);
        
        $current = &$array;
        foreach ($parts as $part) {
            if (!isset($current[$part]) || !is_array($current[$part])) {
                return; // Path doesn't exist
            }
            $current = &$current[$part];
        }
        
        if (isset($current[$lastKey])) {
            $current[$lastKey] = $this->convertToIntegerString($current[$lastKey]);
        }
    }
}

