<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use JsonSerializable;

/**
 * A `__toString()` value whose `?string` jsonSerialize() can write null: json_encode() reads only jsonSerialize(), so
 * the class is `string | null` wherever it is published.
 */
final class StringableNullableJson implements JsonSerializable
{
    /**
     * Hold the string to serialize, or null.
     */
    public function __construct(private ?string $value = null) {}

    /**
     * The string a Blade echo or concatenation writes.
     */
    public function __toString(): string
    {
        return (string) $this->value;
    }

    /**
     * The string or null json_encode() writes.
     */
    public function jsonSerialize(): ?string
    {
        return $this->value;
    }
}
