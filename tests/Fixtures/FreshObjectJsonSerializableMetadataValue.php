<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use JsonSerializable;

/** @implements JsonSerializable<self> */
final class FreshObjectJsonSerializableMetadataValue implements JsonSerializable
{
    /**
     * Return a new instance every time, so no object identity ever repeats.
     */
    public function jsonSerialize(): self
    {
        return new self;
    }
}
