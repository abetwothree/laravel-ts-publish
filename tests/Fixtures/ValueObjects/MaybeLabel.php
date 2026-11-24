<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** Something whose label may be missing, which SureLabel narrows to always present. */
interface MaybeLabel
{
    /** The label, if any. */
    public function label(): ?string;
}
