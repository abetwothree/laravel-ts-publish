<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

/**
 * A `__toString()` value json_encode() writes as its typed public properties, never as the string.
 */
final class PublicLabelStringable
{
    public string $text = '';

    public ?int $rank = null;

    /**
     * The text a Blade echo writes.
     */
    public function __toString(): string
    {
        return $this->text;
    }
}
