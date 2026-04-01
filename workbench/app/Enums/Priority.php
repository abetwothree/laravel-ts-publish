<?php

declare(strict_types=1);

namespace Workbench\App\Enums;

use AbeTwoThree\LaravelTsPublish\Attributes\TsEnumMethod;
use AbeTwoThree\LaravelTsPublish\Attributes\TsEnumStaticMethod;

/**
 * Int-backed enum with instance methods that return different types per case.
 */
enum Priority: int
{
    case Low = 0;
    case Medium = 1;
    case High = 2;
    case Critical = 3;

    #[TsEnumMethod(description: 'Human-readable label')]
    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low Priority',
            self::Medium => 'Medium Priority',
            self::High => 'High Priority',
            self::Critical => 'Critical Priority',
        };
    }

    #[TsEnumMethod(description: 'Tailwind badge color class')]
    public function badgeColor(): string
    {
        return match ($this) {
            self::Low => 'bg-gray-100 text-gray-800',
            self::Medium => 'bg-blue-100 text-blue-800',
            self::High => 'bg-orange-100 text-orange-800',
            self::Critical => 'bg-red-100 text-red-800',
        };
    }

    #[TsEnumMethod(description: 'Icon name for the priority level')]
    public function icon(): string
    {
        return match ($this) {
            self::Low => 'arrow-down',
            self::Medium => 'minus',
            self::High => 'arrow-up',
            self::Critical => 'exclamation-triangle',
        };
    }

    /** Method that requires a parameter — params provided via attribute */
    #[TsEnumMethod(description: 'Compare with threshold', params: ['threshold' => 1])]
    public function isAboveThreshold(int $threshold): bool
    {
        return $this->value > $threshold;
    }

    /** Method that requires a parameter — no params provided, should be skipped */
    #[TsEnumMethod(description: 'Compare with ceiling')]
    public function isAboveCeiling(int $ceiling): bool
    {
        return $this->value > $ceiling;
    }

    /** Static method that requires a parameter — params provided via attribute */
    #[TsEnumStaticMethod(description: 'Filter by minimum', params: ['minimum' => 1])]
    public static function filterByMinimum(int $minimum): array
    {
        return array_filter(self::cases(), fn (self $case) => $case->value >= $minimum);
    }

    /** Static method that requires a parameter — no params provided, should be skipped */
    #[TsEnumStaticMethod(description: 'Filter by maximum')]
    public static function filterByMaximum(int $maximum): array
    {
        return array_filter(self::cases(), fn (self $case) => $case->value <= $maximum);
    }

    /** Should NOT be published — no TsEnumMethod attribute */
    public function numericWeight(): int
    {
        return ($this->value + 1) * 10;
    }

    /** Should NOT be published — no TsEnumStaticMethod attribute */
    public static function highestValue(): int
    {
        return self::Critical->value;
    }
}
