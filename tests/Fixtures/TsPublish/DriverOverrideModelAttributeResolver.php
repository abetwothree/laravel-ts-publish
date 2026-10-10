<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish;

use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use Override;

/**
 * A test-only resolver that reports one connection driver for every model, or a model's own from a map, so the SQLite
 * schema reads as another driver.
 */
class DriverOverrideModelAttributeResolver extends ModelAttributeResolver
{
    /**
     * Report `$driver` for every model the `$drivers` map does not name.
     *
     * @param  array<class-string, string>  $drivers
     */
    public function __construct(private readonly string $driver, private readonly array $drivers = []) {}

    /** Report the model's driver from the map, else the one this resolver was built with. */
    #[Override]
    public function connectionDriver(string $modelFqcn): ?string
    {
        return $this->drivers[$modelFqcn] ?? $this->driver;
    }
}
