<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui;

use AbeTwoThree\LaravelTsPublish\Attributes\TsType;
use Workbench\App\Packages\Ui\Node;

/**
 * Its own override names a global type with no import, so its module extends that type without importing it.
 */
#[TsType('UiLocal')]
final class Unimported extends Node {}
