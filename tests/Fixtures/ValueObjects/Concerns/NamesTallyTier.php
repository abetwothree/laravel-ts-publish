<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\Concerns;

/** A test-only trait whose method writes the untyped `$tier` of the class that uses it a string. */
trait NamesTallyTier
{
    /** Move the tier to the name passed. */
    public function setTier(string $tier): static
    {
        $this->tier = $tier;

        return $this;
    }
}
