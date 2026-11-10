<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects;

/**
 * An item a sample row holds (GH-154).
 */
final class SampleItem
{
    public function __construct(public string $name = '', public int $size = 0) {}
}
