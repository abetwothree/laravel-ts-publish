<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

/**
 * A minimal middleware stub that has no share() method.
 * Used to test the parseDocblockFromMiddleware() early-return branch.
 */
class MiddlewareWithoutShareMethod
{
    // Intentionally has no share() method.
}
