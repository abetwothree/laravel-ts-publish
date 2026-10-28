<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Concerns\QuietHello;

/**
 * Uses a trait whose override stands alone, so the trait it nests is left out.
 */
final class QuietThing
{
    use QuietHello;
}
