<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Concerns;

/**
 * Brings in another trait's documented property and declares none of its own.
 */
trait StacksGauges
{
    use HoldsGauges;
}
