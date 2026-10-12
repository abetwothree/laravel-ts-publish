<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;

/**
 * A test-only value object whose toArray() keys only numbers, which Model::toArray() writes in its place.
 *
 * @implements Arrayable<int, string>
 */
final class WeekRota implements Arrayable
{
    /**
     * The rota of each weekday, by its number.
     *
     * @return array{1: string, 2: string}
     */
    public function toArray(): array
    {
        return [1 => 'mon', 2 => 'tue'];
    }
}
