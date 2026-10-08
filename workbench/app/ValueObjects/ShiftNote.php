<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects;

use JsonSerializable;

/**
 * A note json_encode() writes through its `?string` jsonSerialize(), so it can be null, while `__toString()` always
 * gives a string.
 */
class ShiftNote implements JsonSerializable
{
    public function __construct(private readonly ?string $text = null) {}

    public function __toString(): string
    {
        return (string) $this->text;
    }

    public function jsonSerialize(): ?string
    {
        return $this->text;
    }
}
