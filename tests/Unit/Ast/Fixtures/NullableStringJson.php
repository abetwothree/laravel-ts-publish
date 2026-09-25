<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use JsonSerializable;

/**
 * A value json_encode() writes as its `?string` jsonSerialize() returns: a string, or null. PHP reflects `string|null`
 * as this same nullable `string`, and pint rewrites that spelling to `?string`.
 */
final class NullableStringJson implements JsonSerializable
{
    /**
     * Hold the string to serialize, or null.
     */
    public function __construct(private ?string $value = null) {}

    /**
     * The string or null json_encode() writes.
     */
    public function jsonSerialize(): ?string
    {
        return $this->value;
    }
}
