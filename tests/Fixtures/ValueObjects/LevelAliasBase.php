<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\Concerns\LevelsTier;

/** A test-only payload that runs its trait's `level()` also as `baseLevel()`, reading this class's LEVEL. */
class LevelAliasBase extends WrittenTallyBase
{
    use LevelsTier {
        level as baseLevel;
    }

    public const LEVEL = 5;
}
