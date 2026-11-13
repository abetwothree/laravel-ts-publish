<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use Workbench\App\Models\Post;

/**
 * A plain class whose nullable `$resource` member is no API resource's proxy, read through `??`.
 */
final class NullableResourceHolder
{
    public ?Post $resource = null;

    /** @var array<string, int> */
    public array $scores = ['x' => 1];

    /**
     * The values its member's relation or a fallback give.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'scores' => $this->resource->comments ?? $this->scores,
            'literal' => $this->resource->comments ?? ['a' => 1],
        ];
    }
}
