<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only runtime class of SelfSpellingBase's `toArray()` that narrows its label to a string. */
final class SelfSpellingChild extends SelfSpellingBase
{
    /** The narrowed label. */
    public function label(): string
    {
        return 'x';
    }
}
