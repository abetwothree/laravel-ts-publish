<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\InertiaUiTable;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\InertiaUiTable\Concerns\RendersPostTable;

/** Takes its table action from a trait declared in another file. */
final class InertiaTraitTableController
{
    use RendersPostTable;
}
