<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use Override;

/**
 * A test-only resolver that reports one connection driver for every model, so the SQLite schema reads as another driver.
 */
class DriverOverrideModelAttributeResolver extends ModelAttributeResolver
{
    public function __construct(private readonly string $driver) {}

    /** Report the driver this resolver was built with. */
    #[Override]
    public function connectionDriver(string $modelFqcn): ?string
    {
        return $this->driver;
    }
}
