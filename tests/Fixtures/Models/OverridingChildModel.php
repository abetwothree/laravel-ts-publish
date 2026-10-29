<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Attributes\TsType;

/**
 * A model whose own override shadows its parent model's.
 */
#[TsType(['type' => 'UiDial', 'import' => '@js/types/ui'])]
class OverridingChildModel extends OverriddenBaseModel {}
