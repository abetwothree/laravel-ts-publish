<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource mapping numbers to a helper's keys a literal spread then overrides, under a `@return` shape.
 *
 * @mixin Post
 */
final class SpreadOverrideMappedResource extends JsonResource
{
    /** @return array{rows: list<array{id: int, by: string}>} */
    public function toArray(Request $request): array
    {
        return ['rows' => array_map(fn (int $n) => [...$this->row($n), ...['by' => $this->title]], [1, 2])];
    }

    /** @return array<string, mixed> */
    private function row(int $n): array
    {
        return ['id' => $n, 'by' => $this->author];
    }
}
