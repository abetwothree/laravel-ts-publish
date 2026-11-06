<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A plain object whose label its declaration types. */
final class LabelledValue
{
    /** A typed label. */
    public function label(): string
    {
        return 'v';
    }
}
