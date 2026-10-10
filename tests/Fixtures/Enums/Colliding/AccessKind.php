<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\Colliding;

/**
 * Publishes the const `AccessKind` in the namespace where the backed Access publishes a type of that name.
 */
enum AccessKind: string
{
    case Direct = 'direct';
    case Delegated = 'delegated';
}
