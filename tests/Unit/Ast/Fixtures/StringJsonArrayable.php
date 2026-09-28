<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * An Arrayable whose jsonSerialize() returns a string, which json_encode() writes in preference to toArray().
 *
 * @implements Arrayable<string, string>
 */
final class StringJsonArrayable implements Arrayable, JsonSerializable
{
    /**
     * The array form, which json_encode() never reads.
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return ['value' => 'x'];
    }

    /**
     * The string json_encode() writes.
     */
    public function jsonSerialize(): string
    {
        return 'x';
    }
}
