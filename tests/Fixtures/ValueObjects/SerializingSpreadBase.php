<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use Illuminate\Queue\SerializesModels;

/** A test-only payload root whose `__serialize()` comes from a Laravel trait. */
class SerializingSpreadBase
{
    use SerializesModels;
}
