<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use BackedEnum;
use UnitEnum;

/** Reads `->value` and `->name` off parameters typed by the enum interfaces, which name no cases. */
final class EnumInterfaceReads
{
    /**
     * The scalars any backed or unit enum holds.
     *
     * @return array<string, mixed>
     */
    public function toArray(BackedEnum $backed, UnitEnum $unit): array
    {
        return ['backed' => $backed->value, 'unit' => $unit->name];
    }
}
