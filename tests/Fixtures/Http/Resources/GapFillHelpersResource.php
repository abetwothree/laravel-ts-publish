<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\GapFillBox;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\GapFillRow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Keys undocumented helpers build, an argument it binds eagerly included, which the caller's `@return` types (GH-154).
 *
 * @property-read GapFillBox $resource
 */
final class GapFillHelpersResource extends JsonResource
{
    /** @return array{names: list<array{name: string|int}>, tags: list<list<int>|null>} */
    public function toArray(Request $request): array
    {
        return [
            ...$this->names(),
            ...$this->tagged(array_map(fn (GapFillRow $r) => $r->ok ? cache('k') : null, $this->resource->rows)),
        ];
    }

    /** Rows whose name falls back to a value no rule types. */
    private function names(): array
    {
        return ['names' => array_map(fn (GapFillRow $r) => ['name' => $r->name ?? cache('k')], $this->resource->rows)];
    }

    /** The tags the caller passes, under their key. */
    private function tagged(array $tags): array
    {
        return ['tags' => $tags];
    }
}
