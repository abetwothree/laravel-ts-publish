<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExclude;
use AbeTwoThree\LaravelTsPublish\Attributes\TsType;
use Workbench\App\Packages\Ui\Node;

/**
 * Would warn that `string` cannot extend `UiNode`, but `#[TsExclude]` keeps the scan from asking.
 */
#[TsExclude]
#[TsType('string')]
final class HiddenSpark extends Node {}
