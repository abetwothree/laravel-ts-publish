<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\Colliding;

/**
 * Publishes the const `AccessType` in the namespace where Access publishes a type of that name.
 */
enum AccessType: string
{
    case Guest = 'guest';
    case Member = 'member';
}
