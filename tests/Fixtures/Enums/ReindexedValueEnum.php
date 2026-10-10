<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums;

use AbeTwoThree\LaravelTsPublish\Attributes\TsEnumMethod;
use AbeTwoThree\LaravelTsPublish\Attributes\TsEnumStaticMethod;
use stdClass;

/** An enum whose method values an EnumResource response re-indexes into a list, or sends as they are. */
enum ReindexedValueEnum: string
{
    case Monthly = 'monthly';

    /**
     * Tiers keyed out of order inside an array, which the response re-indexes too.
     *
     * @return array{tiers: array<int, string>}
     */
    #[TsEnumMethod]
    public function nestedTiers(): array
    {
        return ['tiers' => [2 => 'silver', 5 => 'gold']];
    }

    /**
     * Months keyed by numeric strings, which the response re-indexes as it does integer keys.
     *
     * @return array<string, string>
     */
    #[TsEnumMethod]
    public function months(): array
    {
        return ['01' => 'Jan', '02' => 'Feb'];
    }

    /** An object with only numeric keys, holding an array keyed out of order, which the response never walks into. */
    #[TsEnumMethod]
    public function objectCodes(): stdClass
    {
        return (object) [10 => 'Ten', 20 => [2 => 'silver']];
    }

    /**
     * The same object inside an array, which the response walks into only as far as the object.
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
}
