<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui;

use Workbench\App\Enums\UiTone;
use Workbench\App\Packages\Ui\Gauge;

/**
 * A plain value object holding a class with its own override and an overridden enum, beside a scalar.
 */
final class GaugeToneHolder
{
    public function __construct(
        public Gauge $gauge = new Gauge,
        public UiTone $tone = UiTone::Bright,
        public int $n = 1,
    ) {}
}
