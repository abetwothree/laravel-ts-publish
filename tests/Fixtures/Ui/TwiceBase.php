<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui;

use AbeTwoThree\LaravelTsPublish\Attributes\TsType;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Concerns\Glows;
use Workbench\App\Packages\Ui\HasIcon;

/**
 * A parent that uses the trait and implements the interface its child repeats.
 */
#[TsType(['type' => 'UiBase', 'import' => '@js/types/ui'])]
abstract class TwiceBase implements HasIcon
{
    use Glows;
}
