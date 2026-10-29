<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui;

use AbeTwoThree\LaravelTsPublish\Attributes\TsType;

/**
 * Its own override over a parent's union, which cannot be extended.
 */
#[TsType(['type' => 'UiDial', 'import' => '@js/types/ui'])]
final class DialOverMaybe extends MaybeNode {}
