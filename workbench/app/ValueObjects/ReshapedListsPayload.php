<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects;

/**
 * Fixture: lists a payload reshapes with array_values(), which keeps the element type and drops the keys.
 */
final class ReshapedListsPayload
{
    /** @var list<string> */
    public array $tags = ['a'];

    /** @var array<string, int> */
    public array $counts = ['a' => 1];

    /** @var array{width: int, label: string} */
    public array $size = ['width' => 1, 'label' => 'x'];

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'tags' => array_values($this->tags),
            'count_values' => array_values($this->counts),
            'size_values' => array_values($this->size),
            'kept_tags' => array_values(array_filter($this->tags)),
        ];
    }
}
