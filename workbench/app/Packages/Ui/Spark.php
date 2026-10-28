<?php

declare(strict_types=1);

namespace Workbench\App\Packages\Ui;

use AbeTwoThree\LaravelTsPublish\Attributes\TsType;

/**
 * Its own override cannot be extended, so it publishes `string` and leaves its parent's out with a warning.
 */
#[TsType('string')]
final class Spark extends Node {}
