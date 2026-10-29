<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui;

use AbeTwoThree\LaravelTsPublish\Attributes\TsType;
use Illuminate\Support\Carbon;

/**
 * A Carbon date with an override, which json_encode() would write as its string.
 */
#[TsType(['type' => 'UiStamp', 'import' => '@js/types/ui'])]
final class Stamp extends Carbon {}
