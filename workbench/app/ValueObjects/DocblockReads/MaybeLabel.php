<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects\DocblockReads;

/**
 * Something that may have a label, named by a closure parameter's `@param` (GH-134).
 */
interface MaybeLabel
{
    /**
     * The label, if any.
     */
    public function label(): ?string;
}
