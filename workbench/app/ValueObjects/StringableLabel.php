<?php

namespace Workbench\App\ValueObjects;

/**
 * A simple value object with __toString, used as a fixture for docblock type resolution tests.
 */
class StringableLabel
{
    public function __construct(private readonly string $value) {}

    public function __toString(): string
    {
        return $this->value;
    }
}
