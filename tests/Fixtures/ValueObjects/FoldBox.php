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
     * The same declared shape, from a static call.
     *
     * @return array{by: string, n?: int}
     */
    public static function partialOf(bool $picked): array
    {
        return $picked ? ['by' => 'hi', 'n' => 1] : ['by' => 'hi'];
    }

    /**
     * A prop a page may receive, declared optional.
     *
     * @return array{probe?: string}
     */
    public static function maybeProbe(string $pick): array
    {
        return $pick === 'x' ? ['probe' => 'x'] : [];
    }

    /**
     * An open map that writes `by` among keys no name names.
     *
     * @return array<string, string>
     */
    public function labels(): array
    {
        return ['by' => $this->title, 'tone' => 'warm'];
    }

    /**
     * A shape that declares one key optional, which the body leaves out unless picked.
     *
     * @return array{by: string, n?: int}
     */
    public function partial(bool $picked): array
    {
        return $picked ? ['by' => $this->title, 'n' => 1] : ['by' => $this->title];
    }
}
