<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

/** Inherits both the defaults and the methods that write them, and adds a writer of its own. */
final class InheritedDefaultsSubject extends ReassignedDefaultsSubject
{
    /** Clears the label its parent never writes. */
    public function clear(): void
    {
        $this->label = null;
    }
}
