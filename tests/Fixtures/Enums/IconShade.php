<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums;

use AbeTwoThree\LaravelTsPublish\Attributes\TsType;
use Workbench\App\Packages\Ui\HasIcon;

/**
 * An enum, which its own publisher writes, with an override of its own and an interface's.
 */
#[TsType(['type' => 'UiNode', 'import' => '@js/types/ui'])]
enum IconShade: string implements HasIcon
{
    case Light = 'light';
}
