<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects;

/**
 * A plain flag a child class may give a get hook, beside a final flag and a readonly one, which no child can hook.
 */
class OpenFlags
{
    public bool $open = true;

    final public bool $fixed = true;

    public function __construct(public readonly bool $frozen = true) {}
}
