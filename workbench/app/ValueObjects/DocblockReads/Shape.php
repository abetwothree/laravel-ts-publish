<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects\DocblockReads;

/**
 * An abstract shape that is not a model (GH-134).
 */
abstract class Shape
{
    /**
     * The area.
     */
    abstract public function area(): float;
}
