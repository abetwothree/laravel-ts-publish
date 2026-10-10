<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use JsonSerializable;

/** A test-only value object whose JSON keys only numbers, which an API resource's filter never enters. */
final class WeekSchedule implements JsonSerializable
{
    /**
     * The shift of each weekday, by its number.
     *
     * @return array{1: string, 2: string}
     */
    public function jsonSerialize(): array
    {
        return [1 => 'mon', 2 => 'tue'];
    }
}
