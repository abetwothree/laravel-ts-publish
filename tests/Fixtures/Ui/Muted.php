<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui;

use AbeTwoThree\LaravelTsPublish\Attributes\TsType;
use Workbench\App\Packages\Ui\HasIcon;

/**
 * An interface whose override stands alone with `inherit: false`, over the interface it extends.
 */
#[TsType(['type' => 'UiMuted', 'import' => '@js/types/ui'], inherit: false)]
interface Muted extends HasIcon {}
