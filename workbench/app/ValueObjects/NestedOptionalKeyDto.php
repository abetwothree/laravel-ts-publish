<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Fixture holding an optional key one level below the top: `inner` resolves to an inline shape
 * containing `assignedLater?`, which is what hands shapeValueHasUnimportableToken() a string with a
 * `?:` in it. A top-level optional key never reaches that predicate, so only nesting pins the bug.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class NestedOptionalKeyDto implements Arrayable
{
    public function __construct(
        public DeferredAssignmentDto $inner,
        public string $label,
    ) {}

    /** @return array{inner: DeferredAssignmentDto, label: string} */
    public function toArray(): array
    {
        return ['inner' => $this->inner, 'label' => $this->label];
    }
}
