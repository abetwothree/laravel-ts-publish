<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only subclass of LooseLabel that overrides its untyped label. */
class LooseLabelChild extends LooseLabel
{
    /** The overriding label. */
    public function label()
    {
        return 'x';
    }
}
