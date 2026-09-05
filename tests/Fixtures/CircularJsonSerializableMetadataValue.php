<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use JsonSerializable;

/** @implements JsonSerializable<self> */
final class CircularJsonSerializableMetadataValue implements JsonSerializable
{
    /**
     * Return the same object to simulate circular serialization.
     */
    public function jsonSerialize(): self
    {
        return $this;
    }
}
