<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\Concerns\SpellsSelf;

/** A test-only payload whose trait's `self` names this class, so its string label. */
final class SelfSpellingUser
{
    use SpellsSelf;

    /** The label the trait's `self` reaches. */
    public function label(): string
    {
        return 'x';
    }
}
