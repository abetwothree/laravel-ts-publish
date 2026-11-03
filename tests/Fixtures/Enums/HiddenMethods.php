<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums;

use AbeTwoThree\LaravelTsPublish\Attributes\TsEnumMethod;
use AbeTwoThree\LaravelTsPublish\Attributes\TsEnumStaticMethod;

/**
 * An enum whose attributed instance and static methods are public, private or protected.
 */
enum HiddenMethods: string
{
    case One = 'one';

    /**
     * List every case's value.
     *
     * @return list<string>
     */
    #[TsEnumStaticMethod]
    public static function options(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * A value every case publishes.
     */
    #[TsEnumMethod]
    public function shown(): string
    {
        return 'shown';
    }

    /**
     * A list kept inside the enum.
     *
     * @return list<string>
     */
    #[TsEnumStaticMethod]
    protected static function internalList(): array
    {
        return ['internal'];
    }

    /**
     * A value kept inside the enum.
     */
    #[TsEnumMethod]
    private function secret(): string
    {
        return 'secret';
    }
}
