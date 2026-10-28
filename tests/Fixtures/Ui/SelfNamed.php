<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui;

use AbeTwoThree\LaravelTsPublish\Attributes\TsType;
use Workbench\App\Packages\Ui\Node;

/**
 * Its own override spells its composed type's name with no import, so the module's interface extends itself.
 */
#[TsType('SelfNamedType')]
final class SelfNamed extends Node {}
