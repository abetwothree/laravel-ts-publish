<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A row a resource maps beside a value no rule types: whether it holds one, its id and its name. */
final class GapFillRow
{
    public bool $ok = false;

    public int $id = 0;

    public ?string $name = null;
}
