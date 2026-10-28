<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExclude;
use AbeTwoThree\LaravelTsPublish\Attributes\TsType;
use Workbench\App\Packages\Ui\HasIcon;

/**
 * Composes as `Greets` would, but `#[TsExclude]` opts it out, so it publishes its own override alone.
 */
#[TsExclude]
#[TsType(['type' => 'UiHello', 'import' => '@js/types/ui'])]
interface HiddenGreets extends HasIcon {}
