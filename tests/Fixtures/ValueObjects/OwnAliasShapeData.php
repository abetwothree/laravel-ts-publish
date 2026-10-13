<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;

/**
 * An Arrayable whose toArray() shape names its own `Row` alias, which a same-named alias of the class naming this one
 * must not replace.
 *
 * @phpstan-type Row = array{b: string}
 *
 * @implements Arrayable<string, mixed>
 */
final class OwnAliasShapeData implements Arrayable
{
    /**
     * Serialize the row.
     *
     * @return array{row: Row}
     */
    public function toArray(): array
    {
        return ['row' => ['b' => 'x']];
    }
}
