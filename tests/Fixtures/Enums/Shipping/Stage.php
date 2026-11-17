<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\Shipping;

/**
 * Spelled like Billing\Stage, sharing two of its cases, so the alias both spell admits those two alone.
 */
enum Stage: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Lost = 'lost';
}
