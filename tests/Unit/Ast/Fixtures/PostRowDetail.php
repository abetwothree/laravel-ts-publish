<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

/** The detail PostRowFormatter::row() reads, which no caller of that helper names. */
final class PostRowDetail
{
    /**
     * A literal behind a vague signature.
     *
     * @return array<string, mixed>
     */
    public function detail(): array
    {
        return ['rank' => 1];
    }
}
