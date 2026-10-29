<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui;

use AbeTwoThree\LaravelTsPublish\Attributes\TsType;
use Workbench\App\Packages\Ui\HasIcon;

/**
 * An interface whose own override joins the one it extends, so it composes `GreetsType` as a class would.
 */
#[TsType(['type' => 'UiHello', 'import' => '@js/types/ui'])]
interface Greets extends HasIcon {}
