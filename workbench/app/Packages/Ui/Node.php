<?php

declare(strict_types=1);

namespace Workbench\App\Packages\Ui;

use AbeTwoThree\LaravelTsPublish\Attributes\TsType;
use Illuminate\Contracts\Support\Arrayable;

/**
 * A stand-in UI package's base node, whose override every subclass inherits.
 *
 * @implements Arrayable<string, string>
 */
#[TsType(['type' => 'UiNode', 'import' => '@js/types/ui'])]
abstract class Node implements Arrayable
{
    /** @return array{kind: string} */
    public function toArray(): array
    {
        return ['kind' => 'node'];
    }
}
