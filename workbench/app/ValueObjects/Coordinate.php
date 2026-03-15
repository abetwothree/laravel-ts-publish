<?php

namespace Workbench\App\ValueObjects;

class Coordinate
{
    public function __construct(
        public float $lat,
        public float $lng,
    ) {}

    public static function fromString(string $value): self
    {
        $parts = explode(',', $value);

        return new self(
            lat: (float) ($parts[0] ?? 0),
            lng: (float) ($parts[1] ?? 0),
        );
    }

    public function toString(): string
    {
        return "{$this->lat},{$this->lng}";
    }
}
