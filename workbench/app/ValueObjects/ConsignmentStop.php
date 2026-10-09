<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects;

/** A stop on a consignment leg: json_encode() writes its public properties, `$note` included even when `null`. */
final class ConsignmentStop
{
    public ?string $note = null;

    public function __construct(
        public string $name,
        public float $lat,
        public float $lng,
    ) {}
}
