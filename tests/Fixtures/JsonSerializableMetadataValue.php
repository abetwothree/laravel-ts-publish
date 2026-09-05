<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use JsonSerializable;

/** @implements JsonSerializable<array<string, mixed>> */
final readonly class JsonSerializableMetadataValue implements JsonSerializable
{
    /**
     * Create a JSON-serializable metadata test value.
     *
     * @param  array<string, mixed>  $value
     */
    public function __construct(
        private array $value,
    ) {}

    /**
     * Convert the metadata test value for serialization.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->value;
    }
}
