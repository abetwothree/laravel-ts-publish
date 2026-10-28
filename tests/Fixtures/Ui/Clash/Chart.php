<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Clash;

use AbeTwoThree\LaravelTsPublish\Attributes\TsType;
use Workbench\App\Packages\Ui\Node;

/**
 * Its own override is named like the composed type it publishes, so its module imports that name under an alias.
 */
#[TsType(['type' => 'ChartType', 'import' => '@js/types/ui'])]
final class Chart extends Node {}
