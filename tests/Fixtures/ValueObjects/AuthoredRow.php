<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use Workbench\App\Models\User;

/**
 * One row its author wrote: a plain value object whose author is a published model.
 */
final readonly class AuthoredRow
{
    public function __construct(
        public User $author,
        public ?string $name = null,
    ) {}
}
