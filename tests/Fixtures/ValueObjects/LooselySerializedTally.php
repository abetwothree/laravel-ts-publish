<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\MixedSerializeInterfaceCast;
use Illuminate\Contracts\Database\Eloquent\Castable;

/** A castable whose serializing caster's serialize() declares `mixed`, which says nothing of what it writes. */
final class LooselySerializedTally implements Castable
{
    public function __construct(public int $count = 3) {}

    /**
     * Names the caster by class name.
     *
     * @param  array<int, string>  $arguments
     */
    public static function castUsing(array $arguments): string
    {
        return MixedSerializeInterfaceCast::class;
    }
}
