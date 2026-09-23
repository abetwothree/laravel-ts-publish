<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

/** A report whose body reads PostSummaryDetail's, so typing it reads that file too. */
final class PostSummaryReport
{
    /** A shape built from another vague method's body. */
    public function summary(): array
    {
        return ['detail' => (new PostSummaryDetail)->detail()];
    }
}
