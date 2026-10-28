<?php

declare(strict_types=1);

namespace Workbench\App\Packages\Ui;

use Illuminate\Contracts\Support\Arrayable;

/**
 * The control: no override anywhere, so it keeps the shape its `toArray()` infers.
 *
 * @implements Arrayable<string, int>
 */
final class Plain implements Arrayable
{
    /** @return array{a: int} */
    public function toArray(): array
    {
        return ['a' => 1];
    }
}
