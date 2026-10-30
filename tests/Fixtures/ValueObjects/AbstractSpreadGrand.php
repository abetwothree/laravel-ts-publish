<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only payload root declaring the abstract `extra()` AbstractSpreadBase calls through `parent::`. */
abstract class AbstractSpreadGrand
{
    /** @return array<string, mixed> */
    abstract protected function extra(): array;
}
