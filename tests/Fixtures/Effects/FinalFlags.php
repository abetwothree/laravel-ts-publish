<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects;

/**
 * Flags no child class can hook, beside open flags a step further.
 */
final class FinalFlags
{
    public bool $open = true;

    public ?OpenFlags $inner = null;
}
