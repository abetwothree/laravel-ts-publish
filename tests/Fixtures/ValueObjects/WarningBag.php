<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** Promoted properties typed by the constructor's `@param`, and one whose own `@var` outranks it. */
final class WarningBag
{
    /**
     * Collect the warnings.
     *
     * @param  list<string>  $warnings
     * @param  array<string, mixed>  $context
     * @param  list<string>  $codes
     */
    public function __construct(
        public array $warnings,
        public array $context,
        /** @var list<int> */
        public array $codes = [],
    ) {}
}
