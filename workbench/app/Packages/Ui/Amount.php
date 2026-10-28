<?php

declare(strict_types=1);

namespace Workbench\App\Packages\Ui;

use AbeTwoThree\LaravelTsPublish\Attributes\TsType;

/**
 * A plain value class whose override replaces the shape of its public properties.
 */
#[TsType(['type' => 'UiAmount', 'import' => '@js/types/ui'])]
class Amount
{
    public function __construct(public int $value = 0) {}
}
