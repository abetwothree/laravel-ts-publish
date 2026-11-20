<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects;

use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * An Attribute that runs the getter it is built with, so each of its builders runs the closure it is handed.
 */
class EagerAttribute extends Attribute
{
    /**
     * Runs the getter, then builds the attribute.
     */
    public function __construct(?callable $get = null, ?callable $set = null)
    {
        if ($get !== null) {
            $get();
        }

        parent::__construct($get, $set);
    }
}
