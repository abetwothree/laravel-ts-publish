<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use Workbench\App\Enums\Status;

/**
 * A subject an API resource sends either as its status or as its rows, as its kind says.
 */
final readonly class StatusedCrmRows
{
    public function __construct(
        public Status $status,
        /** @var list<CrmAuthoredRow> */
        public array $rows = [],
        public string $kind = 'rows',
    ) {}
}
