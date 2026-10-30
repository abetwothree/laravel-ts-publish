<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only runtime class of BumpingBase that overrides `bump()`, still running the base's through `parent::`. */
final class ParentBumpChild extends BumpingBase
{
    /** Bump as the base does, then move the tier to silver. */
    public function bump(): void
    {
        parent::bump();
        $this->tier = 'silver';
    }
}
