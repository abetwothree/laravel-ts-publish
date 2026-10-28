<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui;

use AbeTwoThree\LaravelTsPublish\Attributes\TsType;

/**
 * Its own override names a global type with no import, alone, so a value publishes it as written.
 */
#[TsType('UiLocal')]
final class Ambient {}
