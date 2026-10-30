<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only collaborator reading its untyped label, which a subclass overrides, through an `@var static` local. */
class LooseLabel
{
    /** A label no declaration types. */
    public function label()
    {
        return 1;
    }

    /** The label of a local declared `static`. */
    public function viaStatic()
    {
        /** @var static $me */
        $me = $this;

        return $me->label();
    }
}
