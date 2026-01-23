<?php

declare(strict_types=1);

namespace App\Utility\Enums;

use App\Utility\Traits\CommonEnumTrait;
use EmreYarligan\EnumConcern\EnumConcern;

enum ProductStatusEnum: string
{
    use CommonEnumTrait, EnumConcern;

    case Pending = 'pending';
    case Approved = 'approved';
    case InTransit = 'in_transit';
    case OutOfDelivery = 'outfordelivery';
    case Delivered = 'delivered';
    case Completed = 'completed';
    case Rejected = 'rejected';

    public function getName(): string
    {
        return match($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::InTransit => 'In Transit',
            self::OutOfDelivery => 'Out of Delivery',
            self::Delivered => 'Delivered',
            self::Completed => 'Completed',
            self::Rejected => 'Rejected',
        };
    }

    /**
     * Get all status labels as array
     * @return array<string, string>
     */
    public static function getAllLabels(): array
    {
        $labels = [];
        foreach (self::cases() as $case) {
            $labels[$case->value] = $case->getName();
        }
        return $labels;
    }

    /**
     * Get statuses that should be excluded for hardware type
     * @return array<string>
     */
    public static function getHardwareExcludedStatuses(): array
    {
        return [
            self::InTransit->value,
        ];
    }

    /**
     * Get color for status
     * @return string
     */
    public function getColor(): string
    {
        return match($this) {
            self::Pending => '#fef3c7',      // Very light yellow/amber
            self::Approved => '#d1fae5',     // Very light green
            self::InTransit => '#dbeafe',    // Very light blue
            self::OutOfDelivery => '#fce7f3', // Very light pink
            self::Delivered => '#e9d5ff',    // Very light purple
            self::Completed => '#d1fae5',    // Very light green
            self::Rejected => '#fee2e2',     // Very light red
        };
    }

    /**
     * Get text color for status
     * @return string
     */
    public function getTextColor(): string
    {
        return match($this) {
            self::Pending => '#92400e',      // Dark amber
            self::Approved => '#065f46',     // Dark green
            self::InTransit => '#1e40af',     // Dark blue
            self::OutOfDelivery => '#9f1239', // Dark pink/red
            self::Delivered => '#6b21a8',    // Dark purple
            self::Completed => '#065f46',     // Dark green
            self::Rejected => '#991b1b',      // Dark red
        };
    }

    /**
     * Get icon for status
     * @return string
     */
    public function getIcon(): string
    {
        return match($this) {
            self::Pending => 'fa-clock',
            self::Approved => 'fa-check-circle',
            self::InTransit => 'fa-truck',
            self::OutOfDelivery => 'fa-truck-fast',
            self::Delivered => 'fa-box-check',
            self::Completed => 'fa-check-double',
            self::Rejected => 'fa-times-circle',
        };
    }

    /**
     * Get all status colors as array
     * @return array<string, string>
     */
    public static function getAllColors(): array
    {
        $colors = [];
        foreach (self::cases() as $case) {
            $colors[$case->value] = $case->getColor();
        }
        return $colors;
    }

    /**
     * Get all status text colors as array
     * @return array<string, string>
     */
    public static function getAllTextColors(): array
    {
        $colors = [];
        foreach (self::cases() as $case) {
            $colors[$case->value] = $case->getTextColor();
        }
        return $colors;
    }

    /**
     * Get all status icons as array
     * @return array<string, string>
     */
    public static function getAllIcons(): array
    {
        $icons = [];
        foreach (self::cases() as $case) {
            $icons[$case->value] = $case->getIcon();
        }
        return $icons;
    }
}
