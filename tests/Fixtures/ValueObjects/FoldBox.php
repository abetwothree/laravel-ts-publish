<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use Workbench\App\Enums\Status;
use Workbench\App\Models\User;
use Workbench\Crm\Enums\Status as CrmStatus;
use Workbench\Crm\Models\User as CrmUser;

/**
 * A test-only holder the literal-fold resources read: an author as an app and a CRM user, a title and two statuses.
 */
final class FoldBox
{
    /**
     * Holds the author twice over, the title and the two statuses.
     */
    public function __construct(
        public User $author,
        public CrmUser $crmAuthor,
        public string $title,
        public Status $status,
        public CrmStatus $crmStatus,
    ) {}

    /**
     * An open map that writes `by` among keys no name names.
     *
     * @return array<string, string>
     */
    public function labels(): array
    {
        return ['by' => $this->title, 'tone' => 'warm'];
    }
}
