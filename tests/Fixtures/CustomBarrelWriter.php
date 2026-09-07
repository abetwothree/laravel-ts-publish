<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Writers\BarrelWriter;

/** A custom barrel_writer_class that overrides nothing; partial runs must still work for it. */
final class CustomBarrelWriter extends BarrelWriter {}
