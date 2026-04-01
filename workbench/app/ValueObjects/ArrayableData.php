<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;

/**
 * A simple value object implementing Arrayable, used as a fixture for docblock type resolution tests.
 *
 * @implements Arrayable<string, mixed>
 */
class ArrayableData implements Arrayable
{
    public function toArray(): array
    {
        return [];
    }
}
