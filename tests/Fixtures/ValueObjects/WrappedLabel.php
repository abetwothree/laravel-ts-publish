<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A label json_encode() writes as its one public property. */
final class WrappedLabel
{
    public function __construct(public string $text) {}
}
