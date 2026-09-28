<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Support\Carbon;

/**
 * A Carbon date whose jsonSerialize() override json_encode() writes as an array rather than Carbon's ISO string.
 */
final class ArrayJsonCarbon extends Carbon
{
    /**
     * Serialize as an object holding the ISO string.
     *
     * @return array{iso: string}
     */
    public function jsonSerialize(): array
    {
        return ['iso' => $this->toIso8601String()];
    }
}
