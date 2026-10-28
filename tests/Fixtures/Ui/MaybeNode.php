<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui;

use AbeTwoThree\LaravelTsPublish\Attributes\TsType;

/**
 * An override that is already nullable.
 */
#[TsType(['type' => 'UiNode | null', 'import' => '@js/types/ui'])]
class MaybeNode {}
