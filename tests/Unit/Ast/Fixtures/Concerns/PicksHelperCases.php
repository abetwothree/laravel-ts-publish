<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\Concerns;

use Workbench\App\Enums\Priority as Level;

/** Helpers whose `Level` is this file's import and whose `self` is the class using the trait. */
trait PicksHelperCases
{
    /** A case of the enum this trait imports as `Level`. */
    public function traitHigh(): mixed
    {
        return Level::High;
    }

    /** The case the using class's constant holds. */
    public function traitFallback(): mixed
    {
        return self::FALLBACK;
    }
}
