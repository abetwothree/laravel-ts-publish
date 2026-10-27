<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

/**
 * A test-only runtime class of BaseLateReadsResource's `toArray()`: it redeclares the constant and the property that
 * body reads through `static::` and `$this->`, and declares a public namesake of the private property and `$badge`.
 */
class LateReadsResource extends BaseLateReadsResource
{
    public const KEY = 'child_key';

    public bool $secret = true;

    public $badge = 5;

    protected $tier = 3;
}
