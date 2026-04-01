<?php

declare(strict_types=1);

namespace Workbench\App\Enums;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCase;
use AbeTwoThree\LaravelTsPublish\Attributes\TsEnumMethod;

/**
 * String-backed enum with TsCase attribute overrides on individual cases.
 */
enum Color: string
{
    #[TsCase(description: 'Primary red color')]
    case Red = 'red';

    #[TsCase(description: 'Primary green color')]
    case Green = 'green';

    #[TsCase(description: 'Primary blue color')]
    case Blue = 'blue';

    #[TsCase(name: 'Yellow', value: 'yellow', description: 'Warning yellow')]
    case Amber = 'amber';

    #[TsCase(name: 'Slate', value: 'slate')]
    case Gray = 'gray';

    case Purple = 'purple';

    #[TsEnumMethod(description: 'Get the hex code for the color')]
    public function hex(): string
    {
        return match ($this) {
            self::Red => '#EF4444',
            self::Green => '#22C55E',
            self::Blue => '#3B82F6',
            self::Amber => '#F59E0B',
            self::Gray => '#64748B',
            self::Purple => '#A855F7',
        };
    }

    #[TsEnumMethod(description: 'Get the RGB tuple')]
    public function rgb(): array
    {
        return match ($this) {
            self::Red => [239, 68, 68],
            self::Green => [34, 197, 94],
            self::Blue => [59, 130, 246],
            self::Amber => [245, 158, 11],
            self::Gray => [100, 116, 139],
            self::Purple => [168, 85, 247],
        };
    }
}
