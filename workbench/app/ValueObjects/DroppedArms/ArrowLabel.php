<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects\DroppedArms;

use AbeTwoThree\LaravelTsPublish\Attributes\TsType;

/**
 * A value its `#[TsType]` publishes as an arrow (GH-161).
 */
#[TsType('() => string')]
final class ArrowLabel {}
