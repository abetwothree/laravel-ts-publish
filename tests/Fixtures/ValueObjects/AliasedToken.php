<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use AbeTwoThree\LaravelTsPublish\Attributes\TsType;
use JsonSerializable;
use Stringable;

/** A token whose JSON and string are its code, published as an imported type the app declares. */
#[TsType(['type' => 'AliasedTokenType', 'import' => '@js/types/tokens'])]
final class AliasedToken implements JsonSerializable, Stringable
{
    public function __construct(public string $code) {}

    /** The code, as PHP converts it to a string. */
    public function __toString(): string
    {
        return $this->code;
    }

    /** The code, as json_encode() writes it. */
    public function jsonSerialize(): string
    {
        return $this->code;
    }
}
