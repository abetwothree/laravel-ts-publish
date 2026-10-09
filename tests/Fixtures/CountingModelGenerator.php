<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Generators\ModelGenerator;
use Override;

/** A ModelGenerator that counts its builds, so a test can prove a cache hit never calls generate(). */
class CountingModelGenerator extends ModelGenerator
{
    public static int $built = 0;

    /**
     * Counts the build, then publishes as ModelGenerator does.
     */
    #[Override]
    public function generate(): string
    {
        self::$built++;

        return parent::generate();
    }
}
