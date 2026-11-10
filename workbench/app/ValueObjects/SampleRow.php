<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects;

/**
 * A row of a sample sheet, with a hint that has no type (GH-154).
 */
final class SampleRow
{
    public $hint;

    public function __construct(
        public SampleItem $item,
        public bool $ok = false,
        public ?string $n = null,
        public ?string $x = null,
        public int $id = 0,
    ) {}
}
