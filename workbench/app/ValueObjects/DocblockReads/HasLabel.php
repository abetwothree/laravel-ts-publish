<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects\DocblockReads;

/**
 * Something with a label, named by a closure parameter's `@param` or native type (GH-134).
 */
interface HasLabel
{
    /**
     * The label.
     */
    public function label(): string;
}
