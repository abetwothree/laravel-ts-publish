<?php

declare(strict_types=1);

namespace Workbench\App\Packages\Ui;

use AbeTwoThree\LaravelTsPublish\Attributes\TsType;

/**
 * An interface whose override every implementing class inherits.
 */
#[TsType(['type' => 'UiIcon', 'import' => '@js/types/ui'])]
interface HasIcon {}
