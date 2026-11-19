<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects;

/**
 * A subclass of LiteralGetters that unsets a property its parent's getter returns, so PHP reads it through `__get()`.
 */
class UnsettingLiteralGetters extends LiteralGetters
{
    /**
     * Unsets the key's property.
     */
    public function __construct()
    {
        unset($this->keyName);
    }
}
