<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui;

/**
 * An enum in a composed class's namespace, so the enum and class-type phases write one barrel.
 */
enum GadgetShade: string
{
    case Light = 'light';
    case Dark = 'dark';
}
