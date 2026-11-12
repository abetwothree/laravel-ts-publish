<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/**
 * The rows an API resource maps, a promoted `@var list<AuthorEditorRow>`.
 */
final readonly class AuthorEditorRows
{
    public function __construct(
        /** @var list<AuthorEditorRow> */
        public array $rows = [],
    ) {}
}
