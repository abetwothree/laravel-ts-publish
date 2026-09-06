<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;

/**
 * A property-inferred DTO holding one optional and one required key, so the emitted shape shows both
 * markers side by side. Nested by NestedOptionalKeyDto to put a `?:` inside a shape *value*.
 *
 * @implements Arrayable<string, string>
 */
final class DeferredAssignmentDto implements Arrayable
{
    /** Assigned by the constructor body, so neither defaulted nor promoted: the key emits optional. */
    public string $assignedLater;

    public function __construct(
        public string $promoted,
    ) {
        $this->assignedLater = $promoted;
    }

    public function toArray(): array
    {
        return ['assignedLater' => $this->assignedLater, 'promoted' => $this->promoted];
    }
}
