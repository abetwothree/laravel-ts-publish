<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Concerns\Glows;
use Workbench\App\Packages\Ui\HasIcon;

/**
 * Repeats its parent's trait and interface, so each is reached twice.
 */
final class Twice extends TwiceBase implements HasIcon
{
    use Glows;
}
