<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects;

use Attribute;

/**
 * Marks a property hook, so the hook's first line is the attribute's rather than its own.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class HookMarker {}
