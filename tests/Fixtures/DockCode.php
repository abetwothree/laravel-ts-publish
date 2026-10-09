<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Stringable;

/** A Stringable value whose string differs from its public property, for a `Rule::in()` value list. */
final class DockCode implements Stringable
{
    protected string $prefix = 'D';

    public function __construct(
        public string $code,
    ) {}

    /** The code `In::__toString()` compares a field against. */
    public function __toString(): string
    {
        return $this->prefix.$this->code;
    }
}
