<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/**
 * One reviewed row: a CRM author and an app editor, two classes one name spells, beside its scalars.
 */
final readonly class ReviewRow
{
    public function __construct(
        public CrmUser $author,
        public User $editor,
        public string $title,
        public int $n,
        public ?string $note = null,
    ) {}
}
