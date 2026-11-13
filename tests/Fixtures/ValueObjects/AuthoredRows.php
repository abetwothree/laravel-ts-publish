<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/**
 * The rows an API resource maps, a promoted `@var list<AuthoredRow>`.
 */
final readonly class AuthoredRows
{
    public function __construct(
        /** @var list<AuthoredRow> */
        public array $rows = [],
    ) {}
}
