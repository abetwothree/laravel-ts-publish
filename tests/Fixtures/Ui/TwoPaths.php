<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui;

use AbeTwoThree\LaravelTsPublish\Attributes\TsType;
use Workbench\App\Packages\Ui\Node;

/**
 * Its own override imports its parent's type name from another module, so the module imports one name twice.
 */
#[TsType(['type' => 'UiNode', 'import' => '@js/types/ui-next'])]
final class TwoPaths extends Node {}
