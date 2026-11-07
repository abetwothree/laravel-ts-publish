<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use Workbench\App\Enums\Status;

/**
 * The rows an API resource maps, a promoted `@var list<ReviewRow>`, beside a status it may send instead.
 */
final readonly class ReviewRows
{
    public function __construct(
        public Status $status,
        /** @var list<ReviewRow> */
        public array $rows = [],
        public string $kind = 'rows',
    ) {}
}
