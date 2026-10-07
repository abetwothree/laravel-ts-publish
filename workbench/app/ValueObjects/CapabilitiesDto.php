<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Fixture: readonly DTO with promoted typed props and a generic toArray()
 * docblock — the shape must come from the properties.
 *
 * @implements Arrayable<string, bool|string|null>
 */
final readonly class CapabilitiesDto implements Arrayable
{
    public function __construct(
        public string $typeName,
        public bool $tracksSteelDetails,
        public ?string $warehouseDocsKey = null,
    ) {}

    /** @return array<string, bool|string|null> */
    public function toArray(): array
    {
        return (array) $this;
    }
}
