<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use DateTime;
use JsonSerializable;

/**
 * A DateTime that json_encode() writes as an array, through its own jsonSerialize().
 */
final class ArrayJsonDate extends DateTime implements JsonSerializable
{
    /**
     * Serialize as an object holding the date.
     *
     * @return array{date: string}
     */
    public function jsonSerialize(): array
    {
        return ['date' => $this->format('Y-m-d')];
    }
}
