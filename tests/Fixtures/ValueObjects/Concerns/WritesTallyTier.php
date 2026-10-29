<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\Concerns;

/** A test-only trait whose method writes the untyped `$tier` of the class that uses it a number. */
trait WritesTallyTier
{
    /** Move the tier to the number passed. */
    public function setTier(int $tier): static
    {
        $this->tier = $tier;

        return $this;
    }
}
