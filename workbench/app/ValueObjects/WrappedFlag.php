<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects;

use AbeTwoThree\LaravelTsPublish\Attributes\TsType;
use JsonSerializable;

/**
 * A flag PHP holds as an object, always truthy, that json_encode() writes as its boolean.
 */
#[TsType('boolean')]
final class WrappedFlag implements JsonSerializable
{
    public function __construct(public readonly bool $on) {}

    public function jsonSerialize(): bool
    {
        return $this->on;
    }
}
