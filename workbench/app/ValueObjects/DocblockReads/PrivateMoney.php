<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects\DocblockReads;

/**
 * A money amount whose state is private, so only its methods read it (GH-134).
 */
final class PrivateMoney
{
    public function __construct(private int $cents) {}

    /**
     * The amount as a decimal string.
     */
    public function formatted(): string
    {
        return number_format($this->cents / 100, 2);
    }
}
