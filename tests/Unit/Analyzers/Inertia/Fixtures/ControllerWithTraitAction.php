<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\Concerns\RendersHelperPage;

/** Takes its only action from a trait declared in another file. */
final class ControllerWithTraitAction
{
    use RendersHelperPage;
}
