<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects;

/**
 * A subclass of LiteralGetters that unsets a property its parent's getter returns, so PHP reads it through `__get()`,
 * and another by a quoted name.
 */
class UnsettingLiteralGetters extends LiteralGetters
{
    /**
     * Unsets the key's property and the names.
     */
    public function __construct()
    {
        unset($this->keyName, $this->{'names'});
    }
}
