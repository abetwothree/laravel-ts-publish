<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use Workbench\Crm\Models\User as CrmUser;

/**
 * A reviewed box: its CRM author and count, beside the rows it maps.
 */
final readonly class ReviewBox
{
    public function __construct(
        public CrmUser $author,
        public int $n,
        /** @var list<ReviewRow> */
        public array $rows = [],
    ) {}
}
