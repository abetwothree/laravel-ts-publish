<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects;

use stdClass;

/**
 * Fixture: arrays a payload reshapes with array_values() or an (object) or (array) cast, and stdClass values.
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
            'counts_object' => (object) $this->counts,
            'tags_object' => (object) $this->tags,
            'size_object' => (object) $this->size,
            'empty_object' => (object) [],
            'scalar_object' => (object) 'x',
            'counts_array' => (array) $this->counts,
            'collection_object' => (object) collect($this->tags),
            'blank' => $this->blank(),
            'fresh' => new stdClass,
        ];
    }

    /** An empty object, declared as the class it is built from. */
    public function blank(): stdClass
    {
        return new stdClass;
    }
}
