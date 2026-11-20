<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/**
 * Flags whose `__set()` clears the pin on every write it is handed, beside a public property a write sets in place and
 * a protected one a write from outside hands to it.
 */
final class MagicSetFlags
{
    public bool $is_pinned = true;

    public ?string $label = null;

    protected ?string $title = null;

    /**
     * Clears the pin.
     */
    public function __set(string $key, mixed $value): void
    {
        $this->is_pinned = false;
    }
}
