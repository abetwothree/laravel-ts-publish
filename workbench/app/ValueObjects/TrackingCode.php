<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects;

use JsonSerializable;

/**
 * A tracking code whose jsonSerialize() differs from its properties, so it publishes as the serialized array.
 */
final class TrackingCode implements JsonSerializable
{
    protected string $carrierToken = 'carrier-token';

    public function __construct(
        public string $code,
    ) {}

    /** @return array{code: string, carrier: string} */
    public function jsonSerialize(): array
    {
        return ['code' => strtoupper($this->code), 'carrier' => 'ups'];
    }
}
