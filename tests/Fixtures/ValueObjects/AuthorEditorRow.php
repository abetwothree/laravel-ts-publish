<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/**
 * One row a CRM user wrote and an app user edited: two classes the same name spells.
 */
final readonly class AuthorEditorRow
{
    public function __construct(
        public CrmUser $author,
        public User $editor,
    ) {}
}
