<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExclude;
use Workbench\App\Packages\Ui\HasIcon;
use Workbench\App\Packages\Ui\Node;

/**
 * Would compose, but `#[TsExclude]` keeps it from publishing a module, so a value of it publishes the nearest override.
 */
#[TsExclude]
final class HiddenGadget extends Node implements HasIcon {}
