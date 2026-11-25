<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects\DocblockReads;

/**
 * A box with an untyped public property beside a typed method (GH-134).
 */
final class LooseBox
{
    public $raw = 'x';

    /**
     * The label.
     */
    public function label(): string
    {
        return 'L';
    }
}
