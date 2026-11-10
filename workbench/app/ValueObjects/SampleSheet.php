<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects;

/**
 * Rows and names the join, boundary and placeholder probes map (GH-154).
 */
final class SampleSheet
{
    public function __construct(
        /** @var list<SampleRow> */
        public array $rows = [],
        public int $id = 0,
        /** @var list<string> */
        public array $names = [],
    ) {}
}
