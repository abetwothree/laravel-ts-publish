<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects;

/** One leg of a consignment, nesting an optional stop. */
final class ConsignmentLeg
{
    public function __construct(
        public string $code,
        public int $sequence,
        public ?ConsignmentStop $stop = null,
    ) {}
}
