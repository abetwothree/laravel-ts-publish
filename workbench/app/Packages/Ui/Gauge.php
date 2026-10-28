<?php

declare(strict_types=1);

namespace Workbench\App\Packages\Ui;

use AbeTwoThree\LaravelTsPublish\Attributes\TsType;

/**
 * Its own override stands alone with `inherit: false`, so it publishes `UiGauge` as written.
 */
#[TsType(['type' => 'UiGauge', 'import' => '@js/types/ui'], inherit: false)]
final class Gauge extends Node {}
