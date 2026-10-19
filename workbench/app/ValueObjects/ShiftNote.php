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
    /**
     * Hold the note's text, or null for no note.
     */
    public function __construct(private readonly ?string $text = null) {}

    /**
     * The text a Blade echo writes.
     */
    public function __toString(): string
    {
        return (string) $this->text;
    }

    /**
     * The text or null json_encode() writes.
     */
    public function jsonSerialize(): ?string
    {
        return $this->text;
    }
}
