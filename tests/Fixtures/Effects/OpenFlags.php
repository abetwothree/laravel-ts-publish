<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects;

/**
 * A plain flag a child class may give a get hook, beside a final flag and a readonly one, which no child can hook, and
 * a private one no read from outside the class reaches.
 */
class OpenFlags
{
    public bool $open = true;

    final public bool $fixed = true;

    private bool $secret = true;

    public function __construct(public readonly bool $frozen = true) {}
}
