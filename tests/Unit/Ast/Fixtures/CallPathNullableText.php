<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use JsonSerializable;
use Stringable;

/** A Stringable whose `?string` jsonSerialize() json_encode() writes as a string or null. */
final class CallPathNullableText implements JsonSerializable, Stringable
{
    /** Hold the text, or null. */
    public function __construct(private ?string $value = null) {}

    /** The text, or an empty string. */
    public function __toString(): string
    {
        return (string) $this->value;
    }

    /** The text or null json_encode() writes. */
    public function jsonSerialize(): ?string
    {
        return $this->value;
    }
}
