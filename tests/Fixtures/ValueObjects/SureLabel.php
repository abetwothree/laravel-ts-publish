<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A label that is always present, narrowing MaybeLabel's return. */
final class SureLabel implements MaybeLabel
{
    public function __construct(private string $text) {}

    /** The label. */
    public function label(): string
    {
        return $this->text;
    }
}
