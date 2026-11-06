<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use Workbench\App\Enums\Priority;
use Workbench\App\Enums\Status;

/** A leg a cast's `get()` lists: a scalar, an int-backed enum and a nullable one, all public and promoted. */
final class StatusLeg
{
    public function __construct(
        public string $code,
        public Status $status,
        public ?Priority $priority = null,
    ) {}
}
