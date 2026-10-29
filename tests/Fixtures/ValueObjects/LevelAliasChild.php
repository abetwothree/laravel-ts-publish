<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\Concerns\LevelsTier;

/** A test-only runtime class whose own copy of its trait's `level()` never runs, while the inherited alias does. */
final class LevelAliasChild extends LevelAliasBase
{
    use LevelsTier;

    public const LEVEL = true;

    /** Move the tier to silver. */
    public function level(): void
    {
        $this->tier = 'silver';
    }
}
