<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

/** The detail PostSummaryReport reads, one body further down. */
final class PostSummaryDetail
{
    /** A literal behind a vague signature. */
    public function detail(): array
    {
        return ['views' => 1];
    }
}
