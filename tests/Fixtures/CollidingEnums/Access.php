<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\CollidingEnums;

/**
 * Publishes the type `AccessType`, the name the AccessType enum beside it publishes its const under.
 */
enum Access: string
{
    case Read = 'read';
    case Write = 'write';
}
