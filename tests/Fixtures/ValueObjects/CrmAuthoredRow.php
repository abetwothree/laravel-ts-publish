<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use Workbench\Crm\Models\User;

/**
 * One row a CRM user wrote, whose author is named like the app's own user model.
 */
final readonly class CrmAuthoredRow
{
    public function __construct(
        public User $author,
        public ?string $name = null,
    ) {}
}
