<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Attributes\TsType;
use Illuminate\Database\Eloquent\Model;

/**
 * A parent model whose override its child models inherit.
 */
#[TsType(['type' => 'UiNode', 'import' => '@js/types/ui'])]
abstract class OverriddenBaseModel extends Model {}
