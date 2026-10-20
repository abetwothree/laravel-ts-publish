<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums;

use AbeTwoThree\LaravelTsPublish\Attributes\TsEnumMethod;
use AbeTwoThree\LaravelTsPublish\Attributes\TsEnumStaticMethod;
use stdClass;

/** An enum whose method values hold numeric keys, which an EnumResource response sends as json_encode() writes them. */
enum ReindexedValueEnum: string
{
    case Monthly = 'monthly';

    /**
     * A static value keyed out of order.
     *
     * @return array<int, string>
     */
    #[TsEnumStaticMethod]
    public static function sparse(): array
    {
        return [3 => 'three', 7 => 'seven'];
    }

    /**
     * Tiers keyed out of order inside an array, which the response keeps too.
     *
     * @return array{tiers: array<int, string>}
     */
    #[TsEnumMethod]
    public function nestedTiers(): array
    {
        return ['tiers' => [2 => 'silver', 5 => 'gold']];
    }

    /**
     * Months keyed by numeric strings, which the response keeps as it does integer keys.
     *
     * @return array<string, string>
     */
    #[TsEnumMethod]
    public function months(): array
    {
        return ['01' => 'Jan', '02' => 'Feb'];
    }

    /** An object with only numeric keys, holding an array keyed out of order. */
    #[TsEnumMethod]
    public function objectCodes(): stdClass
    {
        return (object) [10 => 'Ten', 20 => [2 => 'silver']];
    }

    /**
     * The same object inside an array.
     *
     * @return array{codes: stdClass}
     */
    #[TsEnumMethod]
    public function nestedObjectCodes(): array
    {
        return ['codes' => (object) [10 => 'Ten', 20 => [2 => 'silver']]];
    }

    /**
     * Keys that mix numbers and names, which the response keeps.
     *
     * @return array<int|string, string>
     */
    #[TsEnumMethod]
    public function mixedKeys(): array
    {
        return [1 => 'one', 'two' => 'two'];
    }
}
