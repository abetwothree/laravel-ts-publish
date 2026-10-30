<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only runtime class of UntypedLabelBase's `toArray()` that overrides its untyped label and tag. */
final class UntypedLabelChild extends UntypedLabelBase
{
    public $tag = 'x';

    /** The overriding label. */
    public function label()
    {
        return 'x';
    }
}
