<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/**
 * The rows an API resource maps, a promoted `@var list<CrmAuthoredRow>`.
 */
final readonly class CrmAuthoredRows
{
    public function __construct(
        /** @var list<CrmAuthoredRow> */
        public array $rows = [],
    ) {}
}
