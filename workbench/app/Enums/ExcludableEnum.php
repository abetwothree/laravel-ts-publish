<?php

declare(strict_types=1);

namespace Workbench\App\Enums;

use AbeTwoThree\LaravelTsPublish\Attributes\TsEnumMethod;
use AbeTwoThree\LaravelTsPublish\Attributes\TsEnumStaticMethod;
use AbeTwoThree\LaravelTsPublish\Attributes\TsExclude;

/**
 * Enum with methods excluded via #[TsExclude] — tests method-level exclusion
 * when auto_include is enabled and when explicit attributes are present.
 */
enum ExcludableEnum: string
{
    case Alpha = 'alpha';
    case Beta = 'beta';

    /** Included — no exclusion attribute */
    public function label(): string
    {
        return match ($this) {
            self::Alpha => 'Alpha Label',
            self::Beta => 'Beta Label',
        };
    }

    /** Excluded via #[TsExclude] — should not appear in TS output */
    #[TsExclude]
    public function secret(): string
    {
        return 'hidden';
    }

    /** Excluded — #[TsExclude] wins over #[TsEnumMethod] */
    #[TsEnumMethod]
    #[TsExclude]
    public function overridden(): string
    {
        return 'should not appear';
    }

    /** Included — no exclusion attribute */
    public static function allLabels(): array
    {
        return array_map(fn (self $case) => $case->label(), self::cases());
    }

    /** Excluded via #[TsExclude] — should not appear in TS output */
    #[TsExclude]
    public static function internalOnly(): array
    {
        return ['internal'];
    }

    /** Excluded — #[TsExclude] wins over #[TsEnumStaticMethod] */
    #[TsEnumStaticMethod]
    #[TsExclude]
    public static function overriddenStatic(): array
    {
        return ['should not appear'];
    }
}
