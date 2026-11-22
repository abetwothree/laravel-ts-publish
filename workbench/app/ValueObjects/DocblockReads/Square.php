<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects\DocblockReads;

/**
 * A square whose side is private (GH-134).
 */
final class Square extends Shape
{
    public function __construct(private float $side) {}

    /**
     * The area.
     */
    public function area(): float
    {
        return $this->side * $this->side;
    }
}
