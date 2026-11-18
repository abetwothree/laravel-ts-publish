<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects;

use AbeTwoThree\LaravelTsPublish\Attributes\TsType;
use JsonSerializable;

/**
 * A mode PHP holds as an object, never identical to a string, that json_encode() writes as one of two strings.
 */
#[TsType("'on' | 'off'")]
final class WrappedMode implements JsonSerializable
{
    public function __construct(public readonly string $mode) {}

    public function jsonSerialize(): string
    {
        return $this->mode;
    }
}
