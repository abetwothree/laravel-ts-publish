<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Concerns;

use AbeTwoThree\LaravelTsPublish\Attributes\TsType;

/**
 * A trait a class and its parent both use.
 */
#[TsType(['type' => 'UiGlow', 'import' => '@js/types/ui'])]
trait Glows {}
