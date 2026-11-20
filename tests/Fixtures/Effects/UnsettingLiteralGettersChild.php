<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects;

/**
 * A subclass whose own constructor runs the parent's, which unsets a property the grandparent's getter returns.
 */
class UnsettingLiteralGettersChild extends UnsettingLiteralGetters
{
    /**
     * Runs the parent's constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }
}
