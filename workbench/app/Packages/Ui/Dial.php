<?php

declare(strict_types=1);

namespace Workbench\App\Packages\Ui;

use AbeTwoThree\LaravelTsPublish\Attributes\TsType;

/**
 * Its own override joins its parent's, so it publishes the composed `DialType`.
 */
#[TsType(['type' => 'UiDial', 'import' => '@js/types/ui'])]
final class Dial extends Node {}
