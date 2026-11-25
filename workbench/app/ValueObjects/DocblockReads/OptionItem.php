<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects\DocblockReads;

/**
 * An option whose id is a typed public property, which the `@var X[]` probes key by name or list (GH-134).
 */
class OptionItem
{
    public function __construct(public int $itemId = 7) {}
}
