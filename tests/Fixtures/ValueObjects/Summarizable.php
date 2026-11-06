<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** An interface a value object implements and no model does. */
interface Summarizable
{
    /** A one-line summary. */
    public function summary(): string;
}
