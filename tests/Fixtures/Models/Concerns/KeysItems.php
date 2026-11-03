<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\Concerns;

/**
 * A trait whose key template is declared twice, as `@template` and `@phpstan-template`, ahead of its item template.
 *
 * @template TKey
 *
 * @phpstan-template TKey of array-key
 *
 * @template TItem
 */
trait KeysItems
{
    /** @return TItem|null */
    public function firstItem(): mixed
    {
        return null;
    }
}
