<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** What a resource whose `@return` fills a gap reads: an untyped value, an untyped list and typed rows. */
final class GapFillBox
{
    /** @var mixed an untyped value */
    public $loose;

    public array $plain = [];

    /** @var list<GapFillRow> */
    public array $rows = [];
}
