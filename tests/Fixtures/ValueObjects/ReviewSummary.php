<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/**
 * A review's subject, a user or a post, beside a summary it may lack and the rows it maps.
 */
final readonly class ReviewSummary
{
    public function __construct(
        public User|Post $subject,
        public ?string $summary = null,
        /** @var list<ReviewRow> */
        public array $rows = [],
    ) {}
}
