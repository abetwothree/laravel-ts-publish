<?php

namespace Workbench\App\Enums;

use AbeTwoThree\LaravelTsPublish\Attributes\TsEnum;
use AbeTwoThree\LaravelTsPublish\Attributes\TsEnumMethod;
use AbeTwoThree\LaravelTsPublish\Attributes\TsEnumStaticMethod;

/**
 * Doc block description that should be overridden by the attribute description.
 */
#[TsEnum(name: 'Season', description: 'The four seasons of the year')]
enum Season: string
{
    case Spring = 'spring';
    case Summer = 'summer';
    case Autumn = 'autumn';
    case Winter = 'winter';

    #[TsEnumMethod(description: 'Average temperature in Celsius')]
    public function avgTemp(): int
    {
        return match ($this) {
            self::Spring => 15,
            self::Summer => 30,
            self::Autumn => 12,
            self::Winter => -5,
        };
    }

    /**
     * This method throws for certain cases.
     * Used to test the catch branch in transformMethods.
     */
    #[TsEnumMethod(description: 'Only works for warm seasons')]
    public function warmGreeting(): string
    {
        if ($this === self::Winter) {
            throw new \RuntimeException('Winter has no warm greeting');
        }

        return match ($this) {
            self::Spring => 'Enjoy the blooms!',
            self::Summer => 'Stay cool!',
            self::Autumn => 'Enjoy the leaves!',
            default => '',
        };
    }

    /**
     * Static method that always throws.
     * Used to test the catch branch in transformStaticMethods.
     */
    #[TsEnumStaticMethod(description: 'This always throws')]
    public static function broken(): never
    {
        throw new \RuntimeException('Always fails');
    }
}
